<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Normalizer;

use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\vs_voting\Service\VotingQuestionManager;

/**
 * Normalizes question paragraphs for API responses.
 */
final class QuestionNormalizer {

  /**
   * QuestionNormalizer constructor.
   *
   * @param \Drupal\vs_voting\Service\VotingQuestionManager $questionManager
   *   The question manager.
   * @param \Drupal\vs_voting\Normalizer\AnswerNormalizer $answerNormalizer
   *   The answer normalizer.
   */
  public function __construct(
    private readonly VotingQuestionManager $questionManager,
    private readonly AnswerNormalizer $answerNormalizer,
  ) {}

  /**
   * Normalizes a question paragraph with its answers.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $question
   *   The question paragraph.
   * @param \Drupal\paragraphs\ParagraphInterface[] $answers
   *   The answers paragraphs.
   *
   * @return array<string, mixed>
   *   The question payload.
   *
   * @throws MissingDataException
   */
  public function normalize(ParagraphInterface $question, array $answers): array {
    return [
      'id' => (int) $question->id(),
      'title' => $this->questionManager->extractQuestionTitle($question),
      'description' => $this->questionManager->buildTextPayload($question, 'field_description'),
      'answers' => array_map($this->answerNormalizer->normalize(...), $answers),
    ];
  }

}
