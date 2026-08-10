<?php

declare(strict_types=1);

namespace Drupal\vs_voting\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\vs_voting\Service\VotingRequestContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Attaches a correlation id to API requests and responses.
 */
final readonly class RequestCorrelationSubscriber implements EventSubscriberInterface {

  /**
   * RequestCorrelationSubscriber constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingRequestContext $requestContext
   *   The request context.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    private VotingRequestContext $requestContext,
    private UuidInterface        $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run before Drupal authentication (priority 300) so even auth failures
      // carry a correlation id in the JSON body and response headers.
      KernelEvents::REQUEST => ['onRequest', 301],
      KernelEvents::RESPONSE => ['onResponse', -200],
    ];
  }

  /**
   * Attaches a correlation id to API requests and responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   *
   * @return void
   *   No returns.
   */
  public function onRequest(RequestEvent $event): void {
    if (! $event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    $header = trim((string) $request->headers->get('X-Request-ID', ''));

    $request_id = $this->isValidRequestId($header) ? $header : $this->uuid->generate();

    $request->attributes->set(VotingRequestContext::REQUEST_ID_ATTRIBUTE, $request_id);
  }


  /**
   * Handles the response event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   *
   * @return void
   *  No returns.
   */
  public function onResponse(ResponseEvent $event): void {
    if (! $event->isMainRequest()) {
      return;
    }

    $request_id = $this->requestContext->getRequestId();

    if ($request_id) {
      $event->getResponse()->headers->set('X-Request-ID', $request_id);
    }
  }


  /**
   * Checks if the given request ID is valid.
   *
   * The request ID must be a non-empty string, not longer than 128 characters,
   * and must match the regular expression /^[A-Za-z0-9._:-]+$/.
   *
   * @param string $requestId
   *   The request ID to validate.
   *
   * @return bool
   *   TRUE if the request ID is valid, FALSE otherwise.
   */
  private function isValidRequestId(string $requestId): bool {
    return $requestId !== '' &&
      strlen($requestId) <= 128 &&
      preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) === 1;
  }

}
