<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Extracts question and answer content for the API layer.
 */
final class VotingQuestionManager {

  /**
   * VotingQuestionManager constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   */
  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Extracts the question title from a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $question
   *   The question paragraph.
   *
   * @return string|null
   *   The question title or NULL if the field is not present.
   */
  public function extractQuestionTitle(ParagraphInterface $question): ?string {
    if ($question->hasField('field_question') && !$question->get('field_question')->isEmpty()) {
      return (string) $question->get('field_question')->value;
    }
    return NULL;
  }

  /**
   * Extracts the answer title from a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $answer
   *   The answer paragraph.
   *
   * @return string|null
   *   The answer title or NULL if the field is not present.
   */
  public function extractAnswerTitle(ParagraphInterface $answer): ?string {
    if ($answer->hasField('field_answer') && !$answer->get('field_answer')->isEmpty()) {
      return (string) $answer->get('field_answer')->value;
    }
    return NULL;
  }

  /**
   * Builds the answer text payload when configured.
   *
   * @param ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param string $fieldName
   *   The answer field name (e.g., 'field_answer').
   *
   * @return array<string, string>|null
   *   The text payload or NULL.
   *
   * @throws MissingDataException
   */
  public function buildTextPayload(ParagraphInterface $paragraph, string $fieldName): ?array {
    if (! $paragraph->hasField($fieldName) || $paragraph->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $item = $paragraph->get($fieldName)->first();
    if (! $item || !isset($item->value)) {
      return NULL;
    }

    $value = (string) $item->value;

    $format = isset($item->format) && is_string($item->format) && $item->format !== '' ? $item->format : 'plain_text';

    $processed_text = [
      '#type' => 'processed_text',
      '#text' => $value,
      '#format' => $format,
    ];

    $processed = (string) $this->renderer->renderInIsolation($processed_text);

    return [
      'value' => $value,
      'format' => $format,
      'processed' => $processed,
    ];
  }

  /**
   * Builds the answer image payload.
   *
   * @param ParagraphInterface $answer
   *   The answer paragraph.
   *
   * @return array<string, string|null>|null
   *   The image payload or NULL.
   */
  public function buildAnswerImagePayload(ParagraphInterface $answer): ?array {
    if (! $answer->hasField('field_image') || $answer->get('field_image')->isEmpty()) {
      return NULL;
    }

    $media = $answer->get('field_image')->entity;

    if (! $media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }

    $item = $media->get('field_media_image')->first();

    $file = $item?->entity;

    if (! $item || ! $file) {
      return NULL;
    }

    return [
      'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
      'alt' => (string) ($item->alt ?? ''),
      'width' => isset($item->width) ? (int) $item->width : NULL,
      'height' => isset($item->height) ? (int) $item->height : NULL,
    ];
  }

}
