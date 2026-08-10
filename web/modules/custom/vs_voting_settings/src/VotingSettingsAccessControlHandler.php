<?php

declare(strict_types=1);

namespace Drupal\vs_voting_settings;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Handles access control for voting settings entities.
 *
 * @package Drupal\vs_voting_settings
 */
final class VotingSettingsAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * VotingSettingsAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(
    EntityInterface $entity,
    $operation,
    AccountInterface $account
  ): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view voting_settings'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit voting_settings')
        ->andIf(AccessResult::allowedIf(!$this->hasVotesForVotingSettings((int) $entity->id()))),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete voting_settings')
        ->andIf(AccessResult::allowedIf(!$this->hasVotesForVotingSettings((int) $entity->id()))),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(
    AccountInterface $account,
    array $context,
    $entity_bundle = NULL
  ): AccessResult {
    return AccessResult::allowedIfHasPermissions(
      $account, ['create voting_settings', 'administer voting_settings'],
      'OR'
    );
  }


  /**
   * Checks if there are any votes for a given voting settings entity.
   *
   * @param int $settings_id
   *   The ID of the voting settings entity.
   *
   * @return bool
   *   TRUE if there are votes for the voting settings entity, FALSE otherwise.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  private function hasVotesForVotingSettings(int $settings_id): bool {
    if ($settings_id <= 0) {
      return FALSE;
    }

    $voting_ids = $this->entityTypeManager->getStorage('voting')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting_settings', $settings_id)
      ->execute();

    if ($voting_ids === []) {
      return FALSE;
    }

    return $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting', array_values($voting_ids), 'IN')
      ->range(0, 1)
      ->execute() !== [];
  }

}
