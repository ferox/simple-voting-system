<?php

declare(strict_types=1);

namespace Drupal\vs_voting;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a votação entity type.
 */
interface VotingInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {}
