<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ensures a user can only vote once per voting.
 */
#[Constraint(
  id: 'UniqueUserVote',
  label: new TranslatableMarkup('Um voto por usuário', [], ['context' => 'Validation']),
  type: ['entity']
)]
final class UniqueUserVoteConstraint extends SymfonyConstraint {

  /**
   * UniqueUserVoteConstraint constructor.
   *
   * @param mixed $options
   *   (optional) The options for the constraint.
   * @param string $message
   *   (optional) The error message.
   * @param array|null $groups
   *   (optional) The validation groups.
   * @param mixed $payload
   *   (optional) The payload.
   */
  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'Você já participou desta votação.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
