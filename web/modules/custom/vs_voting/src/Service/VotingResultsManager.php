<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\vs_voting\Exception\VotingUnavailableException;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Psr\Log\LoggerInterface;

/**
 * Builds aggregated voting results efficiently.
 */
final class VotingResultsManager {

  /**
   * VotingResultsManager constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics service.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly CacheBackendInterface $cacheBackend,
    private readonly VotingParticipationHelper $participationHelper,
    private readonly LoggerInterface $logger,
    private readonly VotingMetrics $metrics,
  ) {}

  /**
   * Retrieves the aggregated voting results.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting for which to retrieve the results.
   *
   * @return array
   *   The aggregated voting results.
   *
   * @throws \Drupal\vs_voting\Exception\VotingUnavailableException
   *   If the voting is not available.
   */
  public function getResults(VotingInterface $voting): array {
    if (!$this->participationHelper->hasValidConfiguration($voting)) {
      $this->metrics->increment('voting_results_rejected_total', ['reason' => 'invalid_configuration']);
      throw new VotingUnavailableException('A votação solicitada não está disponível.');
    }

    $cid = 'voting_results:' . $voting->id();

    if ($cache = $this->cacheBackend->get($cid)) {
      $this->metrics->increment('voting_results_cache_hits_total');
      return $cache->data;
    }

    $this->metrics->increment('voting_results_cache_misses_total');

    $question = $this->participationHelper->getQuestion($voting);

    $answers = $this->participationHelper->getAnswers($voting);

    if (! $question || $answers === []) {
      $this->metrics->increment('voting_results_rejected_total', ['reason' => 'missing_question_or_answers']);
      throw new VotingUnavailableException('A votação solicitada não possui resultados disponíveis.');
    }

    $start = microtime(TRUE);
    $counts = $this->database
      ->select('vote', 'v')
      ->fields('v', ['selected_answer'])
      ->condition('v.voting', (int) $voting->id())
      ->condition('v.status', 1)
      ->addExpression('COUNT(v.id)', 'vote_count')
      ->groupBy('v.selected_answer')
      ->execute()
      ->fetchAllKeyed();
    $this->metrics->observe('voting_results_query_duration_seconds', microtime(TRUE) - $start);

    $totalVotes = 0;

    $normalizedAnswers = [];

    foreach ($answers as $answer) {
      $answerId = (int) $answer->id();

      $votes = isset($counts[$answerId]) ? (int) $counts[$answerId] : 0;

      $totalVotes += $votes;

      $normalizedAnswers[] = [
        'id' => $answerId,
        'title' => $answer->hasField('field_answer') ? (string) $answer->get('field_answer')->value : '',
        'votes' => $votes,
      ];
    }

    foreach ($normalizedAnswers as &$answer) {
      $answer['percentage'] = $totalVotes > 0 ? round(($answer['votes'] / $totalVotes) * 100, 1) : 0.0;
    }

    unset($answer);

    $payload = [
      'voting' => [
        'id' => (int) $voting->id(),
        'uuid' => $voting->uuid(),
        'title' => $voting->label(),
      ],
      'question' => [
        'id' => (int) $question->id(),
        'title' => $question->hasField('field_question') ? (string) $question->get('field_question')->value : '',
      ],
      'total_votes' => $totalVotes,
      'answers' => $normalizedAnswers,
    ];

    $this->cacheBackend->set($cid, $payload, time() + 5);
    $this->metrics->observe('voting_results_total_votes', (float) $totalVotes);

    $this->logger->info('Voting results calculated.', [
      'voting_id' => (int) $voting->id(),
      'total_votes' => $totalVotes,
    ]);

    return $payload;
  }

}
