<?php

declare(strict_types=1);

namespace Drupal\vs_vote;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides an administrative listing for votes.
 */
final class VoteListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Voto');
    $header['voting'] = $this->t('Votação');
    $header['selected_answer'] = $this->t('Resposta selecionada');
    $header['uid'] = $this->t('Usuário');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Data');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\vs_vote\VoteInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->toLink($entity->label());
    $row['voting'] = $entity->get('voting')->entity?->toLink() ?? $this->t('N/A');
    $row['selected_answer'] = $entity->get('selected_answer')->entity?->label() ?? $this->t('N/A');
    $row['uid'] = $entity->get('uid')->entity?->label() ?? $this->t('Anonymous');
    $row['status'] = $entity->get('status')->value ? $this->t('Enabled') : $this->t('Disabled');
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);

    return $row + parent::buildRow($entity);
  }

}
