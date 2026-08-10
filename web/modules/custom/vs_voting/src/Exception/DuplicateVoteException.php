<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Exception;

/**
 * Signals that a user already voted in the current voting.
 */
final class DuplicateVoteException extends \RuntimeException {}
