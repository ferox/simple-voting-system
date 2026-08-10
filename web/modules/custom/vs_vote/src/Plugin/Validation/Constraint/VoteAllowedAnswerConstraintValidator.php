<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the selected answer against the voting configuration.
 */
final class VoteAllowedAnswerConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * VoteAllowedAnswerConstraintValidator constructor.
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
    if (! $value instanceof VoteInterface || !$constraint instanceof VoteAllowedAnswerConstraint) {
      return;
    }

    $voting = $value->get('voting')->entity;

    $selected_answer_id = $value->get('selected_answer')->target_id;

    if (! $voting instanceof VotingInterface || !$selected_answer_id) {
      return;
    }

    if (! $this->participationHelper->isAllowedAnswer($voting, (string) $selected_answer_id)) {
      $this->context->buildViolation($constraint->message)
        ->atPath('selected_answer')
        ->addViolation();
    }
  }

}
