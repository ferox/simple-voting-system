<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the voting entity edit forms.
 */
final class VotingForm extends ContentEntityForm {

  /**
   * VotingForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    private readonly VotingParticipationHelper $participationHelper,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('vs_voting.participation_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\vs_voting\VotingInterface $entity */
    $entity = $this->entity;

    if ($entity->isNew()) {
      return;
    }

    if (! $this->participationHelper->hasVotesForVoting($entity)) {
      return;
    }

    $original = $entity->getOriginal();

    if ($original && $entity->get('voting_settings')->target_id !== $original->get('voting_settings')->target_id) {
      $form_state->setErrorByName(
        'voting_settings',
        $this->t('A configuração da votação não pode ser alterada após o primeiro voto.')
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
        $this->messenger()->addStatus($this->t('New votação %label has been created.', $message_args));
        $this->logger('vs_voting')->notice('New votação %label has been created.', $logger_args);

        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The votação %label has been updated.', $message_args));
        $this->logger('vs_voting')->notice('The votação %label has been updated.', $logger_args);

        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    $form_state->setRedirectUrl($this->entity->toUrl());

    return $result;
  }

}
