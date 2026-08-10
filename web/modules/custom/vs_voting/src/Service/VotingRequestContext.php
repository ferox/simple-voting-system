<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides request-scoped metadata for the voting API.
 */
final class VotingRequestContext {

  /**
   * The attribute name used to store the request ID.
   * This constant is used to ensure consistency across the codebase.
   */
  public const string REQUEST_ID_ATTRIBUTE = 'vs_voting.request_id';

  /**
   * VotingRequestContext constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack used to retrieve the current request.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Retrieves the current request.
   *
   * @return \Symfony\Component\HttpFoundation\Request|null
   *   The current request, or NULL if no request is available.
   */
  public function getRequest(): ?Request {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Retrieves the correlation ID from the current request.
   *
   * @return string|null
   *   The correlation ID, or NULL if no correlation ID is available.
   */
  public function getRequestId(): ?string {
    $request = $this->getRequest();

    return $request?->attributes->get(self::REQUEST_ID_ATTRIBUTE);
  }

  /**
   * Sets the correlation ID on the current request.
   *
   * @param string $requestId
   *   The correlation ID to set.
   *
   * @throws \InvalidArgumentException
   *   If the provided request ID is not a string.
   */
  public function setRequestId(string $requestId): void {
    $request = $this->getRequest();

    if ($request) {
      $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
    }
  }

  /**
   * Clears the correlation ID from the current request.
   *
   * @return void
   *   No returns.
   */
  public function clearRequestId(): void {
    $request = $this->getRequest();

    $request?->attributes->remove(self::REQUEST_ID_ATTRIBUTE);
  }

  /**
   * Retrieves the current idempotency key from the request.
   *
   * The idempotency key is expected to be provided in the 'Idempotency-Key'
   * header of the request.
   *
   * @return string|null
   *   The idempotency key, if provided.
   */
  public function getIdempotencyKey(): ?string {
    $request = $this->getRequest();

    $key = is_string($request?->headers->get('Idempotency-Key'))
      ? trim((string) $request->headers->get('Idempotency-Key'))
      : '';

    return $key !== '' ? $key : NULL;
  }

}
