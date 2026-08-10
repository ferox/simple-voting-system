<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the voting settings label with role-aware linking.
 */
#[ViewsField('voting_settings_display')]
final class VotingSettingsDisplay extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields();
    $this->field_alias = $this->aliases['voting_settings'] ?? $this->field_alias;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    $settings_id = $this->getValue($values, 'voting_settings');

    if (! $settings_id) {
      return '';
    }

    $settings = $this->entityTypeManager->getStorage('voting_settings')->load($settings_id);

    if (! $settings) {
      return '';
    }

    if ($this->currentUser->hasPermission('view voting_settings') || $this->currentUser->hasPermission('administer voting_settings')) {
      return $settings->toLink($settings->label())->toRenderable();
    }

    return $settings->label();
  }

}
