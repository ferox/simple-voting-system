<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller\Api;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\vs_voting\Exception\InvalidAnswerException;
use Drupal\vs_voting\Exception\InvalidJsonException;
use Drupal\vs_voting\Normalizer\VoteNormalizer;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Service\VoteManagerInterface;
use Drupal\vs_voting\Service\VotingApiResponseFactory;
use Drupal\vs_voting\Service\VotingRequestContext;
use Drupal\vs_voting\VotingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes the vote creation endpoint.
 */
final readonly class VoteApiController implements ContainerInjectionInterface {

  /**
   * VoteApiController constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\vs_voting\Service\VoteManagerInterface $voteManager
   *   The vote manager.
   * @param \Drupal\vs_voting\Normalizer\VoteNormalizer $normalizer
   *   The vote normalizer.
   * @param \Drupal\vs_voting\Service\VotingApiResponseFactory $responseFactory
   *   The response factory.
   * @param \Drupal\vs_voting\Service\VotingRequestContext $requestContext
   *   The request context.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics.
   */
  public function __construct(
    private AccountProxyInterface    $currentUser,
    private VoteManagerInterface     $voteManager,
    private VoteNormalizer           $normalizer,
    private VotingApiResponseFactory $responseFactory,
    private VotingRequestContext     $requestContext,
    private VotingMetrics            $metrics,
  ) {}

  /**
   * Creates a VoteApiController object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * The container.
   *
   * @return static
   * A new instance of the VoteApiController.
   * /
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('vs_voting.vote_manager'),
      $container->get('vs_voting.vote_normalizer'),
      $container->get('vs_voting.response_factory'),
      $container->get('vs_voting.request_context'),
      $container->get('vs_voting.metrics'),
    );
  }

  /**
   * Creates a new vote.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   *
   * @throws \JsonException
   */
  public function createVote(Request $request, ?VotingInterface $voting): JsonResponse {
    $start = microtime(TRUE);
    $labels = [
      'endpoint' => 'vote_create',
      'method' => 'POST',
    ];

    try {
      $this->metrics->increment('voting_api_requests_total', $labels);

      if (! $voting) {
        throw new NotFoundHttpException('A votação solicitada não foi encontrada.');
      }

      $payload = json_decode($request->getContent(), TRUE);

      if ($payload === NULL && trim($request->getContent()) !== '' && json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidJsonException('O corpo JSON da requisição é inválido.');
      }

      if (! is_array($payload)) {
        throw new InvalidJsonException('O payload JSON deve ser um objeto.');
      }

      if (! array_key_exists('answer_id', $payload) || ! is_numeric($payload['answer_id'])) {
        throw new InvalidAnswerException('O campo answer_id é obrigatório.');
      }

      $vote = $this->voteManager->castVote(
        $voting,
        (int) $payload['answer_id'],
        $this->currentUser,
        $this->requestContext->getIdempotencyKey(),
        'api',
      );

      $this->metrics->increment('voting_api_success_total', $labels);

      return $this->responseFactory->success($this->normalizer->normalize($vote), 201);
    } finally {
      $this->metrics->observe(
        'voting_api_request_duration_seconds',
        microtime(TRUE) - $start, $labels
      );
    }
  }

}
