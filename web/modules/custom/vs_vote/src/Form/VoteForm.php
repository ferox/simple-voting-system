<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Generic administrative form for votes.
 */
final class VoteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->label()];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Novo voto %label registrado.', $message_args));
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('O voto %label foi atualizado.', $message_args));
        break;

      default:
        throw new \LogicException('Não foi possível registrar o voto.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl());

    return $result;
  }

}
