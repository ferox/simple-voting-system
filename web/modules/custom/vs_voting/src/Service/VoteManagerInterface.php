<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_voting\VotingInterface;

/**
 * Shared vote creation contract for UI and API.
 */
interface VoteManagerInterface {

  /**
   * Casts a vote for a given user.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting entity.
   * @param int $answerId
   *   The ID of the answer to vote for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user voting.
   * @param string|null $idempotencyKey
   *   An optional idempotency key to deduplicate identical votes.
   * @param string $source
   *   The source of the vote, either 'api' or 'ui'.
   *
   * @throws \Drupal\vs_voting\Exception\InvalidAnswerException
   *   Thrown if the answer is invalid.
   * @throws \Drupal\vs_voting\Exception\VotingUnavailableException
   *   Thrown if the voting is unavailable.
   * @throws \Drupal\vs_voting\Exception\DuplicateVoteException
   *   Thrown if a duplicate vote is attempted.
   * @throws \Drupal\vs_voting\Exception\IdempotencyConflictException
   *   Thrown if the same idempotency key was used for a different vote.
   *
   * @return \Drupal\vs_vote\VoteInterface
   *   The created vote entity.
   */
  public function castVote(
    VotingInterface $voting,
    int $answerId,
    AccountInterface $account,
    ?string $idempotencyKey = NULL,
    string $source = 'api',
  ): VoteInterface;

}
