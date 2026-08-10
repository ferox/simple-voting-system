<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ensures the selected answer belongs to the configured voting question.
 */
#[Constraint(
  id: 'VoteAllowedAnswer',
  label: new TranslatableMarkup('Voto permitido para a pergunta', [], ['context' => 'Validation']),
  type: ['entity']
)]
final class VoteAllowedAnswerConstraint extends SymfonyConstraint {

  /**
   * VoteAllowedAnswerConstraint constructor.
   *
   * @param mixed $options
   *   (optional) The options for the constraint.
   * @param string $message
   *   (optional) The message to display upon validation failure.
   * @param array|null $groups
   *   (optional) The validation groups to apply.
   * @param mixed $payload
   *   (optional) The payload for the constraint.
   */
  #[HasNamedArguments]
  public function __construct(
    mixed $options = NULL,
    public string $message = 'A resposta selecionada não pertence a esta votação.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
  }

}
