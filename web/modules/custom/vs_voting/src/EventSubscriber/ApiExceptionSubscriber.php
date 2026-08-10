<?php

declare(strict_types=1);

namespace Drupal\vs_voting\EventSubscriber;

use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Http\Exception\CacheableUnauthorizedHttpException;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\simple_oauth\Exception\OAuthUnauthorizedHttpException;
use Drupal\vs_voting\Exception\DuplicateVoteException;
use Drupal\vs_voting\Exception\IdempotencyConflictException;
use Drupal\vs_voting\Exception\InvalidAnswerException;
use Drupal\vs_voting\Exception\InvalidJsonException;
use Drupal\vs_voting\Exception\VotingUnavailableException;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Service\VotingApiResponseFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts API exceptions into normalized JSON responses.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface {

  /**
   * ApiExceptionSubscriber constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingApiResponseFactory $responseFactory
   *   The factory for generating API responses.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The observability metrics.
   */
  public function __construct(
    private VotingApiResponseFactory $responseFactory,
    private VotingMetrics            $metrics,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  /**
   * Handles the exception event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   *
   * @return void
   */
  public function onException(ExceptionEvent $event): void {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (! str_starts_with($path, '/api/v1/') && ! str_starts_with($path, '/health/')) {
      return;
    }

    $throwable = $event->getThrowable();

    if ($throwable instanceof DuplicateVoteException) {
      $this->recordErrorMetric('already_voted', Response::HTTP_CONFLICT, $path);
      $event->setResponse($this->responseFactory->error(
        'already_voted',
        $throwable->getMessage() ?: 'Você já participou desta votação.',
        Response::HTTP_CONFLICT)
      );

      return;
    }

    if ($throwable instanceof IdempotencyConflictException) {
      $this->recordErrorMetric('idempotency_conflict', Response::HTTP_CONFLICT, $path);
      $event->setResponse($this->responseFactory->error(
        'idempotency_conflict',
        $throwable->getMessage() ?: 'A chave de idempotência já foi usada com outro payload.',
        Response::HTTP_CONFLICT)
      );

      return;
    }

    if ($throwable instanceof InvalidAnswerException) {
      $this->recordErrorMetric('invalid_answer', Response::HTTP_UNPROCESSABLE_ENTITY, $path);
      $event->setResponse($this->responseFactory->error(
        'invalid_answer',
        $throwable->getMessage() ?: 'A resposta informada é inválida.',
        Response::HTTP_UNPROCESSABLE_ENTITY,
        'answer_id')
      );

      return;
    }

    if ($throwable instanceof InvalidJsonException) {
      $this->recordErrorMetric('invalid_json', Response::HTTP_BAD_REQUEST, $path);
      $event->setResponse($this->responseFactory->error(
        'invalid_json',
        $throwable->getMessage() ?: 'O corpo JSON da requisição é inválido.',
        Response::HTTP_BAD_REQUEST)
      );

      return;
    }

    if ($throwable instanceof VotingUnavailableException) {
      $this->recordErrorMetric('voting_unavailable', Response::HTTP_NOT_FOUND, $path);
      $event->setResponse($this->responseFactory->error(
        'voting_unavailable',
        $throwable->getMessage() ?: 'A votação solicitada não está disponível.',
        Response::HTTP_NOT_FOUND)
      );

      return;
    }

    if (
      $throwable instanceof OAuthUnauthorizedHttpException ||
      $throwable instanceof CacheableUnauthorizedHttpException ||
      $throwable instanceof UnauthorizedHttpException
    ) {
      $this->metrics->increment('oauth_authentication_failures_total', ['endpoint' => $this->resolveEndpointName($path)]);
      $this->recordErrorMetric('unauthenticated', Response::HTTP_UNAUTHORIZED, $path);

      $event->setResponse($this->responseFactory->error('unauthenticated',
        'Autenticação OAuth inválida, ausente ou expirada.',
        Response::HTTP_UNAUTHORIZED)
      );

      return;
    }

    if (
      $throwable instanceof CacheableAccessDeniedHttpException ||
      $throwable instanceof AccessDeniedHttpException
    ) {
      $this->recordErrorMetric('access_denied', Response::HTTP_FORBIDDEN, $path);
      $event->setResponse($this->responseFactory->error('access_denied',
        'Você não possui permissão para acessar este recurso.',
        Response::HTTP_FORBIDDEN)
      );

      return;
    }

    if (
      $throwable instanceof ParamNotConvertedException ||
      $throwable instanceof NotFoundHttpException
    ) {
      $this->recordErrorMetric('not_found', Response::HTTP_NOT_FOUND, $path);
      $event->setResponse($this->responseFactory->error(
        'not_found',
        'O recurso solicitado não foi encontrado.',
        Response::HTTP_NOT_FOUND)
      );

      return;
    }

    if (
      $throwable instanceof HttpExceptionInterface &&
      $throwable->getStatusCode() === Response::HTTP_SERVICE_UNAVAILABLE
    ) {
      $this->recordErrorMetric('dependency_unavailable', Response::HTTP_SERVICE_UNAVAILABLE, $path);

      $event->setResponse($this->responseFactory->error(
        'dependency_unavailable',
        'Uma dependência obrigatória está indisponível.',
        Response::HTTP_SERVICE_UNAVAILABLE)
      );

      return;
    }

    $this->recordErrorMetric('internal_error', Response::HTTP_INTERNAL_SERVER_ERROR, $path);

    $event->setResponse($this->responseFactory->error(
      'internal_error',
      'Ocorreu um erro interno ao processar a requisição.',
      Response::HTTP_INTERNAL_SERVER_ERROR)
    );
  }

  /**
   * Records an API error metric.
   *
   * @param string $code
   *   The code.
   * @param int $status
   *   The status.
   * @param string $path
   *   The path
   *
   * @return void
   *  No returns.
   */
  private function recordErrorMetric(string $code, int $status, string $path): void {
    $this->metrics->increment('voting_api_errors_total', [
      'code' => $code,
      'status' => (string) $status,
      'endpoint' => $this->resolveEndpointName($path),
    ]);
  }


  /**
   * Resolves the endpoint name.
   *
   * @param string $path
   *. The path.
   *
   * @return string
   *   The route machine name.
   */
  private function resolveEndpointName(string $path): string {
    if ($path === '/api/v1/votings') {
      return 'voting_collection';
    }

    if (preg_match('#^/api/v1/votings/\d+/votes$#', $path) === 1) {
      return 'vote_create';
    }

    if (preg_match('#^/api/v1/votings/\d+/results$#', $path) === 1) {
      return 'voting_results';
    }

    if (preg_match('#^/api/v1/votings/\d+$#', $path) === 1) {
      return 'voting_detail';
    }

    return match ($path) {
      '/health/live' => 'health_live',
      '/health/ready' => 'health_ready',
      default => 'unknown',
    };
  }

}
