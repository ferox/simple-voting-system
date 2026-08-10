<?php

declare(strict_types=1);

namespace Drupal\vs_voting;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\vs_voting_settings\VotingSettingsInterface;

/**
 * Shared helper for participation flow and vote integrity checks.
 */
final readonly class VotingParticipationHelper {

  /**
   * VotingParticipationHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the voting settings for a voting entity.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting interface.
   *
   * @return \Drupal\vs_voting_settings\VotingSettingsInterface|null
   *   The voting settings entity, or null if the voting entity does not have a
   *   'voting_settings' field or if the field is empty.
   */
  public function getVotingSettings(VotingInterface $voting): ?VotingSettingsInterface {
    if (! $voting->hasField('voting_settings') || $voting->get('voting_settings')->isEmpty()) {
      return NULL;
    }

    $settings = $voting->get('voting_settings')->entity;

    return $settings instanceof VotingSettingsInterface ? $settings : NULL;
  }

  /**
   * Gets the question.
   *
   * @param VotingInterface $voting
   *   The voting interface.
   *
   * @return ParagraphInterface|null
   *   The question paragraph, or NULL if not found.
   */
  public function getQuestion(VotingInterface $voting): ?ParagraphInterface {
    $settings = $this->getVotingSettings($voting);
    if (!$settings || !$settings->hasField('question_answers') || $settings->get('question_answers')->isEmpty()) {
      return NULL;
    }

    $question = $settings->get('question_answers')->entity;
    if ($question instanceof ParagraphInterface && $question->bundle() === 'question') {
      return $question;
    }

    return NULL;
  }

  /**
   * Returns all the answers for a given voting.
   *
   * @param VotingInterface $voting
   *   The voting interface.
   *
   * @return ParagraphInterface[]
   *   An array of answered paragraphs.
   */
  public function getAnswers(VotingInterface $voting): array {
    $question = $this->getQuestion($voting);
    if (! $question
      || !$question->hasField('field_question_answers')
      || $question->get('field_question_answers')->isEmpty()
    ) {
      return [];
    }

    $answers = [];

    foreach ($question->get('field_question_answers')->referencedEntities() as $answer) {
      if ($answer instanceof ParagraphInterface && $answer->bundle() === 'answer') {
        $answers[(string) $answer->id()] = $answer;
      }
    }

    return $answers;
  }

  /**
   * Returns whether a given user has already voted in a given voting.
   *
   * @param VotingInterface $voting
   *   The voting interface.
   * @param int $accountId
   *   The account ID.
   *
   * @return bool
   *   TRUE if the user has already voted, FALSE otherwise.
   */
  public function hasValidConfiguration(VotingInterface $voting): bool {
    if ((int) $voting->get('status')->value !== 1) {
      return FALSE;
    }

    return $this->getQuestion($voting) instanceof ParagraphInterface
      && $this->getAnswers($voting) !== [];
  }

  /**
   * Returns whether a given user is allowed to vote in a given voting.
   *
   * @param VotingInterface $voting
   *   The voting interface.
   * @param int|string $answerId
   *   The answer ID.
   *
   * @return bool
   *   TRUE if the user is allowed to vote, FALSE otherwise.
   */
  public function isAllowedAnswer(VotingInterface $voting, int|string $answerId): bool {
    return array_key_exists(
      (string) $answerId,
      $this->getAnswers($voting)
    );
  }

  /**
   * Returns whether a given user has already voted in a given voting.
   *
   * @param VotingInterface|int $voting
   *   The voting interface or ID.
   * @param int|string $uid
   *   The user ID.
   * @param int|null $excludeVoteId
   *   Optionally, exclude a given vote ID from the result.
   *
   * @return bool
   *   TRUE if the user has already voted, FALSE otherwise.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function hasUserVoted(
    VotingInterface|int $voting,
    int|string $uid,
    ?int $excludeVoteId = NULL
  ): bool {
    $votingId = $voting instanceof VotingInterface ? (int) $voting->id() : $voting;

    if ($votingId <= 0 || $uid <= 0) {
      return FALSE;
    }

    $query = $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting', $votingId)
      ->condition('uid', (int) $uid)
      ->range(0, 1);

    if ($excludeVoteId) {
      $query->condition('id', $excludeVoteId, '<>');
    }

    return $query->execute() !== [];
  }

  /**
   * Returns whether a given user has already voted in a given voting.
   *
   * @param VotingInterface|int $voting
   *   The voting interface or ID.
   * @param int $uid
   *   The user ID.
   * @param int|null $excludeVoteId
   *   Optionally, exclude a given vote ID from the result.
   *
   * @return bool
   *   TRUE if the user has already voted, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasVotesForVoting(VotingInterface|int $voting): bool {
    $votingId = $voting instanceof VotingInterface ? (int) $voting->id() : $voting;

    if ($votingId <= 0) {
      return FALSE;
    }

    $result = $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting', $votingId)
      ->range(0, 1)
      ->execute();

    return $result !== [];
  }

  /**
   * Returns whether a given user has already voted in any of the voting with
   * the given settings.
   *
   * @param VotingSettingsInterface|int $settings
   *   The voting settings interface or ID.
   *
   * @return bool
   *   TRUE if the user has any votes for any of the votings with the given
   *   settings, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasVotesForVotingSettings(VotingSettingsInterface|int $settings): bool {
    $settingsId = $settings instanceof VotingSettingsInterface ? (int) $settings->id() : $settings;
    if ($settingsId <= 0) {
      return FALSE;
    }

    $votingIds = $this->entityTypeManager->getStorage('voting')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting_settings', $settingsId)
      ->execute();

    if ($votingIds === []) {
      return FALSE;
    }

    $result = $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting', array_values($votingIds), 'IN')
      ->range(0, 1)
      ->execute();

    return $result !== [];
  }

}
