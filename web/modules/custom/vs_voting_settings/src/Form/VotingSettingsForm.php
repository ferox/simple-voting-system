<?php

declare(strict_types=1);

namespace Drupal\vs_voting_settings\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the voting settings entity edit forms.
 */
final class VotingSettingsForm extends ContentEntityForm {

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ($this->entity->isNew()) {
      return;
    }

    $original = $this->entityTypeManager
      ->getStorage('voting_settings')
      ->loadUnchanged($this->entity->id());

    if (!$original) {
      return;
    }

    $voting_ids = $this->entityTypeManager->getStorage('voting')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting_settings', $this->entity->id())
      ->execute();

    $question_answers_changed = !$this->entity->get('question_answers')->equals($original->get('question_answers'));

    if ($voting_ids !== [] && $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('voting', array_values($voting_ids), 'IN')
      ->range(0, 1)
      ->execute() !== [] && $question_answers_changed) {
      $form_state->setErrorByName(
        'question_answers',
        $this->t('A pergunta e as respostas da votação não podem ser alteradas após o primeiro voto.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus(
          $this->t(
            'New voting settings %label has been created.',
            $message_args
          )
        );
        $this->logger('vs_voting_settings')
          ->notice('New voting settings %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t(
          'The voting settings %label has been updated.',
          $message_args)
        );
        $this->logger('vs_voting_settings')->notice(
          'The voting settings %label has been updated.',
          $logger_args
        );
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl());

    return $result;
  }

}
