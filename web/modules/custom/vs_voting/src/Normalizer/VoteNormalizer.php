<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Normalizer;

use Drupal\vs_vote\VoteInterface;

/**
 * Normalizes votes for API responses.
 */
final class VoteNormalizer {

  /**
   * Normalizes votes for API responses.
   *
   * @param \Drupal\vs_vote\VoteInterface $vote
   *   The vote to normalize.
   *
   * @return array<string, mixed>
   *   The vote payload.
   */
  public function normalize(VoteInterface $vote): array {
    return [
      'id' => (int) $vote->id(),
      'uuid' => $vote->uuid(),
      'voting_id' => (int) $vote->get('voting')->target_id,
      'answer_id' => (int) $vote->get('selected_answer')->target_id,
      'created' => gmdate('c', (int) $vote->getCreatedTime()),
    ];
  }

}
