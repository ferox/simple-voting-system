<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Plugin\views\field;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\vs_voting\VotingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the voting title with role-aware linking.
 */
#[ViewsField('voting_title_display')]
final class VotingTitleDisplay extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AccountProxyInterface $currentUser,
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
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
    $this->field_alias = $this->aliases['title'] ?? $this->field_alias;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    $entity = $values->_entity ?? NULL;
    $title = (string) $this->getValue($values, 'title');

    if (! $entity instanceof VotingInterface) {
      return $title;
    }

    if ($this->currentUser->hasPermission('view voting') || $this->currentUser->hasPermission('administer voting')) {
      return $entity->toLink($entity->label())->toRenderable();
    }

    return $title !== '' ? $title : $entity->label();
  }

}
