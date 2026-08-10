<?php

declare(strict_types=1);

namespace Drupal\vs_voting_settings;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a voting settings entity type.
 */
interface VotingSettingsInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {}
