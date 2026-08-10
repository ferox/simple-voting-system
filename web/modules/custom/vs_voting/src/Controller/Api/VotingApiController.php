<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller\Api;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Service\VotingApiResponseFactory;
use Drupal\vs_voting\Service\VotingRepository;
use Drupal\vs_voting\Normalizer\VotingNormalizer;
use Drupal\vs_voting\VotingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes voting collection and detail endpoints.
 */
final readonly class VotingApiController implements ContainerInjectionInterface {

  /**
   * VotingApiController constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\vs_voting\Service\VotingRepository $repository
   *   The voting repository.
   * @param \Drupal\vs_voting\Normalizer\VotingNormalizer $normalizer
   *   The voting normalizer.
   * @param \Drupal\vs_voting\Service\VotingApiResponseFactory $responseFactory
   *   The response factory.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics.
   */
  public function __construct(
    private AccountProxyInterface    $currentUser,
    private VotingRepository         $repository,
    private VotingNormalizer         $normalizer,
    private VotingApiResponseFactory $responseFactory,
    private VotingMetrics            $metrics,
  ) {}

  /**
   * Creates a VotingApiController object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   *   A new instance of the VotingApiController.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('vs_voting.repository'),
      $container->get('vs_voting.voting_normalizer'),
      $container->get('vs_voting.response_factory'),
      $container->get('vs_voting.metrics'),
    );
  }

  /**
   * Returns a collection of voting.
   *
   * @return JsonResponse
   *   The JSON response.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function collection(): JsonResponse {
    $start = microtime(TRUE);

    $labels = [
      'endpoint' => 'voting_collection',
      'method' => 'GET',
    ];

    try {
      $this->metrics->increment('voting_api_requests_total', $labels);

      $list_of_voting = $this->repository->loadAvailableForUser();

      $data = array_map(
        fn (VotingInterface $voting): array =>
        $this->normalizer->normalizeSummary($voting, $this->currentUser), $list_of_voting
      );

      $this->metrics->increment('voting_api_success_total', $labels);

      return $this->responseFactory->success($data, 200, ['total' => count($data)]);
    } finally {
      $this->metrics->observe('voting_api_request_duration_seconds', microtime(TRUE) - $start, $labels);
    }
  }

  /**
   * Returns a voting detail.
   *
   * @param VotingInterface|null $voting
   *   The voting.
   *
   * @return JsonResponse
   *   The JSON response.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws MissingDataException
   * @throws \JsonException
   */
  public function detail(?VotingInterface $voting): JsonResponse {
    $start = microtime(TRUE);

    $labels = [
      'endpoint' => 'voting_detail',
      'method' => 'GET',
    ];

    try {
      $this->metrics->increment('voting_api_requests_total', $labels);

      if (! $voting) {
        throw new NotFoundHttpException('A votação solicitada não foi encontrada.');
      }

      $this->repository->assertAvailable($voting);

      $this->metrics->increment('voting_api_success_total', $labels);

      return $this->responseFactory->success($this->normalizer->normalizeDetail($voting, $this->currentUser));
    } finally {
      $this->metrics->observe('voting_api_request_duration_seconds', microtime(TRUE) - $start, $labels);
    }
  }

}
