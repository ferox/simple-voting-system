<?php

declare(strict_types=1);

namespace Drupal\vs_vote;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a vote entity type.
 */
interface VoteInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Returns the vote creation timestamp.
   *
   * @return int
   *   The Unix timestamp for when the vote was created.
   */
  public function getCreatedTime(): int;

}
