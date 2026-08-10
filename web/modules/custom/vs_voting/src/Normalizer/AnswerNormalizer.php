<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Normalizer;

use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\vs_voting\Service\VotingQuestionManager;

/**
 * Normalizes answer paragraphs for API responses.
 */
final readonly class AnswerNormalizer {

  /**
   * AnswerNormalizer constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingQuestionManager $questionManager
   *   The voting question manager.
   */
  public function __construct(
    private VotingQuestionManager $questionManager,
  ) {}

  /**
   * Normalizes an answer paragraph.
   *
   * @return array<string, mixed>
   *   The answer payload.
   *
   * @throws MissingDataException
   */
  public function normalize(ParagraphInterface $answer): array {
    return [
      'id' => (int) $answer->id(),
      'title' => $this->questionManager->extractAnswerTitle($answer),
      'description' => $this->questionManager->buildTextPayload($answer, 'field_description'),
      'image' => $this->questionManager->buildAnswerImagePayload($answer),
    ];
  }

}
