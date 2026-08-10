<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller\Api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Service\VotingApiResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exposes API health checks.
 */
final readonly class HealthCheckController implements ContainerInjectionInterface {

  /**
   * HealthCheckController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\vs_voting\Service\VotingApiResponseFactory $responseFactory
   *   The response factory.
   */
  public function __construct(
    private Connection                 $database,
    private EntityTypeManagerInterface $entityTypeManager,
    private ModuleHandlerInterface     $moduleHandler,
    private ConfigFactoryInterface     $configFactory,
    private VotingApiResponseFactory   $responseFactory,
    private VotingMetrics              $metrics,
  ) {}

  /**
   * Creates a new instance of the HealthCheckController.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   *   A new instance of the controller.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('vs_voting.response_factory'),
      $container->get('vs_voting.metrics'),
    );
  }

  /**
   * Liveliness probe.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   *
   * @throws \JsonException
   */
  public function live(): JsonResponse {
    $this->metrics->increment('healthcheck_requests_total', [
      'probe' => 'live',
      'status' => 'ok',
    ]);

    return $this->responseFactory->success(['status' => 'ok']);
  }

  /**
   * Readiness probe.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   *
   * @throws \JsonException
   */
  public function ready(): JsonResponse {
    $start = microtime(TRUE);
    $errors = [];

    try {
      $this->database->query('SELECT 1')->fetchField();
      $this->entityTypeManager
        ->getStorage('vote')
        ->getQuery()
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
    } catch (\Throwable) {
      $errors[] = [
        'code' => 'database_unavailable',
        'message' => 'A conexão com o banco de dados não está pronta.',
        'field' => NULL,
      ];
    }

    if (! $this->moduleHandler->moduleExists('simple_oauth')) {
      $errors[] = [
        'code' => 'oauth_module_disabled',
        'message' => 'O módulo Simple OAuth não está habilitado.',
        'field' => NULL,
      ];
    } else {
      $oauth = $this->configFactory->get('simple_oauth.settings');
      if (! $oauth->get('public_key') || !$oauth->get('private_key')) {
        $errors[] = [
          'code' => 'oauth_keys_missing',
          'message' => 'As chaves pública e privada do Simple OAuth ainda não foram configuradas.',
          'field' => NULL,
        ];
      }
    }

    if ($errors !== []) {
      $this->metrics->increment('healthcheck_requests_total', [
        'probe' => 'ready',
        'status' => 'failed',
      ]);
      $this->metrics->increment('healthcheck_failures_total', ['probe' => 'ready']);
      $this->metrics->observe('healthcheck_request_duration_seconds', microtime(TRUE) - $start, ['probe' => 'ready']);

      return $this->responseFactory->errors($errors, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    $this->metrics->increment('healthcheck_requests_total', [
      'probe' => 'ready',
      'status' => 'ok',
    ]);
    $this->metrics->observe('healthcheck_request_duration_seconds', microtime(TRUE) - $start, ['probe' => 'ready']);

    return $this->responseFactory->success(['status' => 'ready']);
  }

}
