<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a user only has one vote per voting.
 */
final class UniqueUserVoteConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * UniqueUserVoteConstraintValidator constructor.
   *
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   */
  public function __construct(
    private readonly VotingParticipationHelper $participationHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('vs_voting.participation_helper'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (! $value instanceof VoteInterface || ! $constraint instanceof UniqueUserVoteConstraint) {
      return;
    }

    $voting_id = $value->get('voting')->target_id;

    $uid = $value->getOwnerId();

    if (! $voting_id || !$uid) {
      return;
    }

    $exclude = $value->isNew() ? NULL : (int) $value->id();

    if ($this->participationHelper->hasUserVoted((int) $voting_id, (int) $uid, $exclude)) {
      $this->context->buildViolation($constraint->message)
        ->atPath('selected_answer')
        ->addViolation();
    }
  }

}
