<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the answer title from the selected answer paragraph.
 */
#[ViewsField('vote_answer_title')]
final class VoteAnswerTitle extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $answer_id = $this->getValue($values, 'selected_answer');

    if (! $answer_id) {
      return '';
    }

    $answer = $this->entityTypeManager->getStorage('paragraph')->load($answer_id);

    if (! $answer || ! $answer->hasField('field_answer') || $answer->get('field_answer')->isEmpty()) {
      return '';
    }

    return (string) $answer->get('field_answer')->value;
  }

}

