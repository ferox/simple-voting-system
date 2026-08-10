<?php

declare(strict_types=1);

namespace Drupal\vs_voting;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the votação entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class VotingAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The voting participation helper.
   *
   * @param EntityTypeInterface $entity_type
   *   The entity type interface.
   * @param VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    private readonly VotingParticipationHelper $participationHelper,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('vs_voting.participation_helper'),
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
      'view' => AccessResult::allowedIfHasPermissions(
        $account, ['view voting', 'access voting api'], 'OR'
      ),
      'update' => AccessResult::allowedIfHasPermission(
        $account, 'edit voting'
      ),
      'delete' => AccessResult::allowedIfHasPermission(
        $account, 'delete voting'
      )->andIf(AccessResult::allowedIf(!$this->participationHelper->hasVotesForVoting($entity->id()))),
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
      $account, ['create voting', 'administer voting'],
      'OR'
    );
  }

}
