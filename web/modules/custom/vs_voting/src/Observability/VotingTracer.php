<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Observability;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\vs_voting\Service\VotingRequestContext;

/**
 * Stores recent traced spans for the voting API.
 */
final class VotingTracer {

  /**
   * The voting trace table name.
   */
  private const string TABLE = 'vs_voting_trace';

  /**
   * VotingTracer constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time object.
   * @param \Drupal\vs_voting\Service\VotingRequestContext $requestContext
   *   The request context object.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics object.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly VotingRequestContext $requestContext,
    private readonly VotingMetrics $metrics,
  ) {}

  /**
   * Executes a callback within a named span.
   *
   * @template T
   *
   * @param string $spanName
   * @param callable():T $callback
   *   The traced callback.
   * @param array<string, scalar|array|null> $context
   *   Span metadata.
   *
   * @return T
   *   The callback result.
   *
   * @throws \JsonException
   * @throws \Throwable
   */
  public function trace(string $spanName, callable $callback, array $context = []): mixed {
    $started_at = $this->time->getCurrentTime();

    $started_microtime = $this->time->getCurrentMicroTime();

    $request_id = isset($context['request_id']) && is_string($context['request_id'])
      ? $context['request_id']
      : $this->requestContext->getRequestId();

    try {
      $result = $callback();

      $this->storeSpan($spanName, $context, $request_id, $started_at, $started_microtime, 'ok');

      return $result;
    } catch (\Throwable $exception) {
      $this->storeSpan(
        $spanName,
        $context,
        $request_id,
        $started_at,
        $started_microtime,
        'error',
        $exception,
      );

      throw $exception;
    }
  }

  /**
   * Returns the most recent spans for the admin dashboard.
   *
   * @param int|null $limit
   *   Maximum number of rows to return, or NULL for all rows.
   * @param int $offset
   *   Zero-based query offset.
   *
   * @return array<int, array<string, mixed>>
   *   Recent traced spans.
   */
  public function getRecentSpans(?int $limit = 100, int $offset = 0): array {
    $query = $this->database->select(self::TABLE, 't')
      ->fields('t')
      ->orderBy('id', 'DESC');

    if ($limit !== NULL) {
      $query->range($offset, $limit);
    }

    $rows = $query
      ->execute()
      ->fetchAll(FetchAs::Associative);

    return array_map(static function (array $row): array {
      $context = json_decode((string) ($row['context_json'] ?? '{}'), TRUE);

      return [
        'id' => (int) $row['id'],
        'request_id' => (string) ($row['request_id'] ?? ''),
        'span_name' => (string) $row['span_name'],
        'status' => (string) $row['status'],
        'duration_ms' => (float) $row['duration_ms'],
        'context' => is_array($context) ? $context : [],
        'exception_class' => $row['exception_class'] !== NULL ? (string) $row['exception_class'] : NULL,
        'exception_message' => $row['exception_message'] !== NULL ? (string) $row['exception_message'] : NULL,
        'created' => (int) $row['created'],
      ];
    }, array_values($rows));
  }

  /**
   * Counts stored spans.
   *
   * @return int
   *   The number of stored spans.
   */
  public function countRecentSpans(): int {
    $count = $this->database->select(self::TABLE, 't')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count !== FALSE ? (int)$count : 0;
  }

  /**
   * Stores a span in the database.
   *
   * @param string $spanName
   *   The name of the span.
   * @param array<string, mixed> $context
   *   The context of the span.
   * @param string|null $requestId
   *   The request ID.
   * @param int $startedAt
   *   The timestamp when the span was started.
   * @param float $startedMicrotime
   *   The microtime when the span was started.
   * @param string $status
   *   The status of the span.
   * @param \Throwable|null $exception
   *   An optional exception.
   *
   * @return void
   *   No returns.
   *
   * @throws \JsonException
   */
  private function storeSpan(
    string $spanName,
    array $context,
    ?string $requestId,
    int $startedAt,
    float $startedMicrotime,
    string $status,
    ?\Throwable $exception = NULL,
  ): void {
    $duration_seconds = max(0, $this->time->getCurrentMicroTime() - $startedMicrotime);
    $duration_ms = round($duration_seconds * 1000, 3);
    $context_json = json_encode($context, JSON_THROW_ON_ERROR);

    $this->database->insert(self::TABLE)
      ->fields([
        'request_id' => $requestId,
        'span_name' => $spanName,
        'status' => $status,
        'context_json' => $context_json,
        'started' => $startedAt,
        'duration_ms' => $duration_ms,
        'exception_class' => $exception !== NULL ? $exception::class : NULL,
        'exception_message' => $exception?->getMessage(),
        'created' => $this->time->getCurrentTime(),
      ])
      ->execute();

    $labels = [
      'span' => $spanName,
      'status' => $status,
    ];

    $this->metrics->increment('voting_trace_spans_total', $labels);
    $this->metrics->observe('voting_trace_duration_seconds', $duration_seconds, $labels);

    if ($exception !== NULL) {
      $this->metrics->increment('voting_trace_failures_total', [
        'span' => $spanName,
        'exception' => $exception::class,
      ]);
    }
  }

}
