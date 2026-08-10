<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\vs_voting\Exception\VotingUnavailableException;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;

/**
 * Loads voting for the API layer.
 */
final class VotingRepository {

  /**
   * VotingRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly VotingParticipationHelper $participationHelper,
  ) {}

  /**
   * Loads all available voting.
   *
   * @return VotingInterface[]
   *   The array of available voting.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function loadAvailableForUser(): array {
    $ids = $this->entityTypeManager->getStorage('voting')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->execute();

    $list_voting = [];

    foreach ($this->entityTypeManager->getStorage('voting')->loadMultiple($ids) as $voting) {
      if ($voting instanceof VotingInterface &&
        $this->participationHelper->hasValidConfiguration($voting)
      ) {
        $list_voting[] = $voting;
      }
    }

    return $list_voting;
  }

  /**
   * Asserts that a voting is available for a given user.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting to check.
   *
   * @return \Drupal\vs_voting\VotingInterface
   *   The voting if available.
   *
   * @throws \Drupal\vs_voting\Exception\VotingUnavailableException
   *   If the voting is not available.
   */
  public function assertAvailable(VotingInterface $voting): VotingInterface {
    if (
      (int) $voting->get('status')->value !== 1 ||
      ! $this->participationHelper->hasValidConfiguration($voting)
    ) {
      throw new VotingUnavailableException('A votação solicitada não está disponível.');
    }

    return $voting;
  }

}
