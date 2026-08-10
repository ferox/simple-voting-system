<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the participate action for a voting row.
 */
#[ViewsField("voting_participate_link")]
final class VotingParticipateLink extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * VotingParticipateLink constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration, $plugin_id, $plugin_definition,
    private readonly VotingParticipationHelper $participationHelper,
    private readonly AccountProxyInterface $currentUser
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('vs_voting.participation_helper'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // This is a computed UI field, not a database column.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $entity = $values->_entity ?? NULL;

    if (! $entity instanceof VotingInterface || !$this->currentUser->hasPermission('participate in voting')) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['vs-voting-participate-link']],
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
      ],
    ];

    if (! $this->participationHelper->hasValidConfiguration($entity)) {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Indisponível'),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];

      return $build;
    }

    if ($this->participationHelper->hasUserVoted($entity, (int) $this->currentUser->id())) {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Voto registrado'),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];

      return $build;
    }

    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Participar'),
      '#url' => Url::fromRoute('vs_voting.voting_participate', ['voting' => $entity->id()]),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
    ];

    CacheableMetadata::createFromObject($entity)->applyTo($build);

    return $build;
  }

}
