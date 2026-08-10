<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller\Api;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\vs_voting\Normalizer\VotingResultsNormalizer;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Service\VotingApiResponseFactory;
use Drupal\vs_voting\Service\VotingResultsManager;
use Drupal\vs_voting\VotingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes aggregated voting results.
 */
final readonly class VotingResultsApiController implements ContainerInjectionInterface {

  /**
   * VotingResultsApiController constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingResultsManager $resultsManager
   *   The voting results manager.
   * @param \Drupal\vs_voting\Normalizer\VotingResultsNormalizer $normalizer
   *   The voting results normalizer.
   * @param \Drupal\vs_voting\Service\VotingApiResponseFactory $responseFactory
   *   The response factory.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics.
   */
  public function __construct(
    private VotingResultsManager     $resultsManager,
    private VotingResultsNormalizer  $normalizer,
    private VotingApiResponseFactory $responseFactory,
    private VotingMetrics            $metrics,
  ) {}

  /**
   * Creates a VotingResultsApiController object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   *   A new instance of the VotingResultsApiController.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('vs_voting.results_manager'),
      $container->get('vs_voting.results_normalizer'),
      $container->get('vs_voting.response_factory'),
      $container->get('vs_voting.metrics'),
    );
  }

  /**
   * Returns the results of a voting.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   *
   * @throws \JsonException
   */
  public function results(?VotingInterface $voting): JsonResponse {
    $start = microtime(TRUE);

    $labels = [
      'endpoint' => 'voting_results',
      'method' => 'GET',
    ];

    try {
      $this->metrics->increment('voting_api_requests_total', $labels);

      $this->metrics->increment('voting_results_requests_total', $labels);

      if (! $voting) {
        throw new NotFoundHttpException('A votação solicitada não foi encontrada.');
      }

      $this->metrics->increment('voting_api_success_total', $labels);

      return $this->responseFactory->success(
        $this->normalizer->normalize($this->resultsManager->getResults($voting))
      );
    } finally {
      $this->metrics->observe(
        'voting_api_request_duration_seconds',
        microtime(TRUE) - $start, $labels
      );
    }
  }

}
