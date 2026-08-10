<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Builds consistent JSON responses for the voting API.
 */
final readonly class VotingApiResponseFactory {

  /**
   * VotingApiResponseFactory constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingRequestContext $requestContext
   *   The request context.
   */
  public function __construct(
    private VotingRequestContext $requestContext,
  ) {}

  /**
   * Builds a JSON response with the given data, status code and metadata.
   *
   * @param mixed $data
   *   The data to be included in the response.
   * @param int $status
   *   The HTTP status code.
   * @param array<string, mixed> $meta
   *   Additional metadata.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function success(mixed $data, int $status = 200, array $meta = []): JsonResponse {
    return $this->build([
      'data' => $data,
      'meta' => $this->withRequestId($meta),
    ], $status);
  }

  /**
   * Builds a JSON response with a single error, status code and metadata.
   *
   * @param array $errors
   *   The errors to be included in the response.
   * @param int $status
   *   The HTTP status code.
   * @param array<string, mixed> $meta
   *   Additional metadata.
   *
   * @return JsonResponse
   *   The JSON response.
   */
  public function errors(array $errors, int $status, array $meta = []): JsonResponse {
    return $this->build([
      'errors' => $errors,
      'meta' => $this->withRequestId($meta),
    ], $status);
  }

  /**
   * Builds a JSON response with a single error, status code and metadata.
   *
   * @param string $code
   *   The error code.
   * @param string $message
   *   The error message.
   * @param int $status
   *   The HTTP status code.
   * @param string|null $field
   *   The field associated with the error.
   * @param array<string, mixed> $meta
   *   Additional metadata.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function error(string $code, string $message, int $status, ?string $field = NULL): JsonResponse {
    return $this->errors([[
      'code' => $code,
      'message' => $message,
      'field' => $field,
    ]], $status);
  }

  /**
   * Builds a JSON response with the given payload, status code and metadata.
   *
   * @param array<string, mixed> $payload
   *   The payload to be included in the response.
   * @param int $status
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  private function build(array $payload, int $status): JsonResponse {
    $response = new JsonResponse($payload, $status);

    $request_id = $this->requestContext->getRequestId();

    if ($request_id) {
      $response->headers->set('X-Request-ID', $request_id);
    }

    $response->headers->set('Cache-Control', 'private, no-store');

    return $response;
  }

  /**
   * Adds the request ID to the metadata.
   *
   * @param array<string, mixed> $meta
   *   The metadata.
   *
   * @return array<string, mixed>
   *   The metadata with the request ID added.
   */
  private function withRequestId(array $meta): array {
    $meta['request_id'] = $this->requestContext->getRequestId();

    return $meta;
  }

}
