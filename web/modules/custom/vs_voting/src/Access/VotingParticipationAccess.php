<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Access;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;

/**
 * Access check for the participation route.
 */
final readonly class VotingParticipationAccess {

  /**
   * VotingParticipationAccess constructor.
   *
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper service.
   */
  public function __construct(
    private VotingParticipationHelper $participationHelper,
  ) {}

  /**
   * Checks the access for the participation route.
   *
   * @param VotingInterface|null $voting
   *   The voting entity, or NULL if not loaded.
   * @param AccountInterface $account
   *   The user for which to check access.
   *
   * @return AccessResult The access result.
   *   The access result.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function access(?VotingInterface $voting, AccountInterface $account): AccessResult {
    if (! $voting) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, 'participate in voting')
      ->cachePerPermissions()
      ->andIf(
        AccessResult::allowedIf($account->isAuthenticated())->cachePerUser()
      )
      ->andIf(
        AccessResult::allowedIf($this->participationHelper->hasValidConfiguration($voting))
          ->addCacheableDependency($voting)
      )
      ->andIf(
        AccessResult::allowedIf(! $this->participationHelper->hasUserVoted($voting, (int) $account->id()))
          ->cachePerUser()
      );
  }

}
