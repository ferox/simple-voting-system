<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Exception;

/**
 * Signals a reused idempotency key with a different payload.
 */
final class IdempotencyConflictException extends \RuntimeException {}
