<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Observability;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;

/**
 * Stores aggregated metrics for the voting API.
 */
final class VotingMetrics {

  /**
   * The voting metrics table name.
   */
  private const string TABLE = 'vs_voting_metric';

  /**
   * The counter type.
   */
  private const string TYPE_COUNTER = 'counter';

  /**
   * The histogram type.
   */
  private const string TYPE_HISTOGRAM = 'histogram';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Increments a counter metric.
   *
   * @param string $metric
   *   The metric name.
   * @param array<string, scalar|null> $labels
   *   Metric labels.
   *
   * @return void
   *   No returns.
   *
   * @throws \JsonException
   */
  public function increment(string $metric, array $labels = []): void {
    [$labels_json, $labels_hash] = $this->serializeLabels($labels);

    $timestamp = $this->time->getCurrentTime();

    $this->database->merge(self::TABLE)
      ->keys([
        'metric_name' => $metric,
        'metric_type' => self::TYPE_COUNTER,
        'labels_hash' => $labels_hash,
      ])
      ->insertFields([
        'metric_name' => $metric,
        'metric_type' => self::TYPE_COUNTER,
        'labels_hash' => $labels_hash,
        'labels_json' => $labels_json,
        'counter_value' => 1,
        'sample_count' => 0,
        'sample_sum' => 0,
        'sample_min' => NULL,
        'sample_max' => NULL,
        'last_value' => 1,
        'created' => $timestamp,
        'changed' => $timestamp,
      ])
      ->updateFields([
        'labels_json' => $labels_json,
        'last_value' => 1,
        'changed' => $timestamp,
      ])
      ->expression('counter_value', 'counter_value + :increment', [':increment' => 1])
      ->execute();
  }

  /**
   * Observes a value for a histogram metric.
   *
   * @param string $metric
   *   The metric.
   * @param float $value
   *  The floating number of the metric.
   * @param array<string, scalar|null> $labels
   *   Metric labels.
   *
   * @return void
   *   No returns.
   *
   * @throws \JsonException
   */
  public function observe(string $metric, float $value, array $labels = []): void {
    [$labels_json, $labels_hash] = $this->serializeLabels($labels);
    $timestamp = $this->time->getCurrentTime();

    $this->database->merge(self::TABLE)
      ->keys([
        'metric_name' => $metric,
        'metric_type' => self::TYPE_HISTOGRAM,
        'labels_hash' => $labels_hash,
      ])
      ->insertFields([
        'metric_name' => $metric,
        'metric_type' => self::TYPE_HISTOGRAM,
        'labels_hash' => $labels_hash,
        'labels_json' => $labels_json,
        'counter_value' => 0,
        'sample_count' => 1,
        'sample_sum' => $value,
        'sample_min' => $value,
        'sample_max' => $value,
        'last_value' => $value,
        'created' => $timestamp,
        'changed' => $timestamp,
      ])
      ->updateFields([
        'labels_json' => $labels_json,
        'last_value' => $value,
        'changed' => $timestamp,
      ])
      ->expression('sample_count', 'sample_count + :sample_increment', [':sample_increment' => 1])
      ->expression('sample_sum', 'sample_sum + :sample_value', [':sample_value' => $value])
      ->expression('sample_min', 'CASE WHEN sample_min IS NULL OR sample_min > :min_value THEN :min_value ELSE sample_min END', [':min_value' => $value])
      ->expression('sample_max', 'CASE WHEN sample_max IS NULL OR sample_max < :max_value THEN :max_value ELSE sample_max END', [':max_value' => $value])
      ->execute();
  }

  /**
   * Returns a single counter value for a metric/label combination.
   *
   * @param string $metric
   *   The matric.
   * @param array<string, scalar|null> $labels
   *   Metric labels.
   *
   * @return int
   *   The counter value.
   *
   * @throws \JsonException
   */
  public function getCounterValue(string $metric, array $labels = []): int {
    [, $labels_hash] = $this->serializeLabels($labels);

    $value = $this->database->select(self::TABLE, 'm')
      ->fields('m', ['counter_value'])
      ->condition('metric_name', $metric)
      ->condition('metric_type', self::TYPE_COUNTER)
      ->condition('labels_hash', $labels_hash)
      ->execute()
      ->fetchField();

    return $value !== FALSE ? (int) $value : 0;
  }

  /**
   * Returns aggregated counter rows for the dashboard.
   *
   * @param int|null $limit
   *   Maximum number of rows to return, or NULL for all rows.
   * @param int $offset
   *   Zero-based query offset.
   *
   * @return array<int, array<string, mixed>>
   *   Counter metrics ordered by recent updates.
   */
  public function getCounterSummaries(?int $limit = 250, int $offset = 0): array {
    $query = $this->database->select(self::TABLE, 'm')
      ->fields('m', [
        'metric_name',
        'labels_json',
        'counter_value',
        'last_value',
        'created',
        'changed',
      ])
      ->condition('metric_type', self::TYPE_COUNTER)
      ->orderBy('changed', 'DESC')
      ->orderBy('metric_name', 'ASC');

    if ($limit !== NULL) {
      $query->range($offset, $limit);
    }

    $rows = $query
      ->execute()
      ->fetchAll(FetchAs::Associative);

    return array_map([$this, 'normalizeMetricRow'], array_values($rows));
  }

  /**
   * Returns aggregated histogram rows for the dashboard.
   *
   * @param int|null $limit
   *   Maximum number of rows to return, or NULL for all rows.
   * @param int $offset
   *   Zero-based query offset.
   *
   * @return array<int, array<string, mixed>>
   *   Histogram metrics ordered by recent updates.
   */
  public function getHistogramSummaries(?int $limit = 250, int $offset = 0): array {
    $query = $this->database->select(self::TABLE, 'm')
      ->fields('m', [
        'metric_name',
        'labels_json',
        'sample_count',
        'sample_sum',
        'sample_min',
        'sample_max',
        'last_value',
        'created',
        'changed',
      ])
      ->condition('metric_type', self::TYPE_HISTOGRAM)
      ->orderBy('changed', 'DESC')
      ->orderBy('metric_name', 'ASC');

    if ($limit !== NULL) {
      $query->range($offset, $limit);
    }

    $rows = $query
      ->execute()
      ->fetchAll(FetchAs::Associative);

    return array_map([$this, 'normalizeMetricRow'], array_values($rows));
  }

  /**
   * Counts stored counter summary rows.
   *
   * @return int
   *   The number of counter summary rows.
   */
  public function countCounterSummaries(): int {
    $count = $this->database->select(self::TABLE, 'm')
      ->condition('metric_type', self::TYPE_COUNTER)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count !== FALSE ? (int) $count : 0;
  }

  /**
   * Counts stored histogram summary rows.
   *
   * @return int
   *   The number of histogram summary rows.
   */
  public function countHistogramSummaries(): int {
    $count = $this->database->select(self::TABLE, 'm')
      ->condition('metric_type', self::TYPE_HISTOGRAM)
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count !== FALSE ? (int) $count : 0;
  }

  /**
   * Normalizes labels and produces a deterministic JSON/hash pair.
   *
   * @param array<string, scalar|null> $labels
   *   Raw labels.
   *
   * @return array{0: string, 1: string}
   *   The JSON payload and its hash.
   *
   * @throws \JsonException
   */
  private function serializeLabels(array $labels): array {
    $normalized = [];
    foreach ($labels as $key => $value) {
      if (!is_string($key) || $key === '') {
        continue;
      }

      $normalized[$key] = match (TRUE) {
        is_bool($value) => $value ? 'true' : 'false',
        is_int($value), is_float($value), is_string($value) => (string) $value,
        $value === NULL => 'null',
        default => NULL,
      };
    }

    $normalized = array_filter($normalized, static fn (mixed $value): bool => $value !== NULL);
    ksort($normalized);

    $json = json_encode($normalized, JSON_THROW_ON_ERROR);

    return [$json, hash('sha256', $json)];
  }

  /**
   * Converts a storage row into a dashboard-ready structure.
   *
   * @param array<string, mixed> $row
   *   The raw row.
   *
   * @return array<string, mixed>
   *   The normalized row.
   */
  private function normalizeMetricRow(array $row): array {
    $labels = json_decode((string) ($row['labels_json'] ?? '{}'), TRUE);

    return [
      'metric_name' => (string) $row['metric_name'],
      'labels' => is_array($labels) ? $labels : [],
      'counter_value' => isset($row['counter_value']) ? (int) $row['counter_value'] : 0,
      'sample_count' => isset($row['sample_count']) ? (int) $row['sample_count'] : 0,
      'sample_sum' => isset($row['sample_sum']) ? (float) $row['sample_sum'] : 0.0,
      'sample_min' => isset($row['sample_min']) ? (float) $row['sample_min'] : NULL,
      'sample_max' => isset($row['sample_max']) ? (float) $row['sample_max'] : NULL,
      'last_value' => isset($row['last_value']) ? (float) $row['last_value'] : NULL,
      'created' => (int) $row['created'],
      'changed' => (int) $row['changed'],
    ];
  }

}
