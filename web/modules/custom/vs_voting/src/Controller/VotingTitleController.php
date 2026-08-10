<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\vs_voting\VotingInterface;

/**
 * Builds dynamic route titles for the voting participation flow.
 */
final class VotingTitleController {

  use StringTranslationTrait;

  /**
   * Builds a dynamic route title for the voting participation flow.
   *
   * @param \Drupal\vs_voting\VotingInterface $voting
   *   The voting for which to generate the title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated title.
   */
  public function participateTitle(VotingInterface $voting): TranslatableMarkup {
    return $this->t('Participar: @voting', ['@voting' => $voting->label()]);
  }

}
