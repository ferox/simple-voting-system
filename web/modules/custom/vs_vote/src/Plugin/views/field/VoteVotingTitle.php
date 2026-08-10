<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the related voting title with role-aware linking.
 */
#[ViewsField('vote_voting_title')]
final class VoteVotingTitle extends FieldPluginBase implements ContainerFactoryPluginInterface {

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
    $this->field_alias = $this->aliases['voting'] ?? $this->field_alias;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    $voting_id = $this->getValue($values, 'voting');

    if (! $voting_id) {
      return '';
    }

    $voting = $this->entityTypeManager->getStorage('voting')->load($voting_id);
    if (! $voting) {
      return '';
    }

    if ($this->currentUser->hasPermission('view voting') || $this->currentUser->hasPermission('administer voting')) {
      return $voting->toLink($voting->label())->toRenderable();
    }

    return $voting->label();
  }

}
