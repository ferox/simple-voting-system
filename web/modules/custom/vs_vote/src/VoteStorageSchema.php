<?php

declare(strict_types=1);

namespace Drupal\vs_vote;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Storage schema handler for votes.
 */
final class VoteStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $base_table = $this->storage->getBaseTable();
    $schema[$base_table]['unique keys']['vote__voting__uid'] = ['voting', 'uid'];
    $schema[$base_table]['unique keys']['vote__uid__idempotency_key'] = ['uid', 'idempotency_key'];
    $schema[$base_table]['indexes']['vote_voting_answer'] = ['voting', 'selected_answer'];
    $schema[$base_table]['indexes']['vote_voting_created'] = ['voting', 'created'];
    $schema[$base_table]['indexes']['vote_uid_created'] = ['uid', 'created'];

    return $schema;
  }

}
