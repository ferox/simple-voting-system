<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Normalizer;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;

/**
 * Normalizes voting entities for API responses.
 */
final class VotingNormalizer {

  /**
   * VotingNormalizer constructor.
   *
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   * @param \Drupal\vs_voting\Normalizer\QuestionNormalizer $questionNormalizer
   *   The question normalizer.
   */
  public function __construct(
    private readonly VotingParticipationHelper $participationHelper,
    private readonly QuestionNormalizer $questionNormalizer,
  ) {}

  /**
   * Normalizes a voting entity for a summary API response.
   *
   * @param VotingInterface $voting
   *   The voting entity.
   * @param AccountInterface $account
   *   The current user.
   *
   * @return array<string, mixed>
   *   The voting summary payload.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function normalizeSummary(VotingInterface $voting, AccountInterface $account): array {
    $question = $this->participationHelper->getQuestion($voting);
    return [
      'id' => (int) $voting->id(),
      'uuid' => $voting->uuid(),
      'title' => $voting->label(),
      'question' => [
        'id' => $question ? (int) $question->id() : NULL,
        'title' => $question && $question->hasField('field_question') ? (string) $question->get('field_question')->value : NULL,
      ],
      'participation' => [
        'allowed' => $this->participationHelper->hasValidConfiguration($voting) && !$this->participationHelper->hasUserVoted($voting, (int) $account->id()),
        'already_voted' => $this->participationHelper->hasUserVoted($voting, (int) $account->id()),
      ],
    ];
  }

  /**
   * Normalizes a voting entity for a detailed API response.
   *
   * @param VotingInterface $voting
   *   The voting entity.
   * @param AccountInterface $account
   *   The current user.
   *
   * @return array<string, mixed>
   *   The voting detail payload.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws MissingDataException
   */
  public function normalizeDetail(VotingInterface $voting, AccountInterface $account): array {
    $question = $this->participationHelper->getQuestion($voting);
    $answers = array_values($this->participationHelper->getAnswers($voting));

    return [
      'id' => (int) $voting->id(),
      'uuid' => $voting->uuid(),
      'title' => $voting->label(),
      'question' => $question ? $this->questionNormalizer->normalize($question, $answers) : NULL,
      'participation' => [
        'allowed' => $this->participationHelper->hasValidConfiguration($voting) && !$this->participationHelper->hasUserVoted($voting, (int) $account->id()),
        'already_voted' => $this->participationHelper->hasUserVoted($voting, (int) $account->id()),
      ],
    ];
  }

}
