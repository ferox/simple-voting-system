<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;
use Drupal\vs_vote\Form\VoteForm;
use Drupal\vs_vote\Form\VotingParticipationForm;
use Drupal\vs_vote\VoteAccessControlHandler;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_vote\VoteListBuilder;
use Drupal\vs_vote\VoteStorageSchema;

/**
 * Defines the individual vote entity.
 */
#[ContentEntityType(
  id: 'vote',
  label: new TranslatableMarkup('Voto'),
  label_collection: new TranslatableMarkup('Votos'),
  label_singular: new TranslatableMarkup('voto'),
  label_plural: new TranslatableMarkup('votos'),
  entity_keys: [
    'id' => 'id',
    'langcode' => 'langcode',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => VoteListBuilder::class,
    'views_data' => EntityViewsData::class,
    'storage_schema' => VoteStorageSchema::class,
    'access' => VoteAccessControlHandler::class,
    'form' => [
      'add' => VoteForm::class,
      'edit' => VoteForm::class,
      'participate' => VotingParticipationForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/votes',
    'add-form' => '/admin/content/votes/add',
    'canonical' => '/admin/content/votes/{vote}',
    'edit-form' => '/admin/content/votes/{vote}/edit',
    'delete-form' => '/admin/content/votes/{vote}/delete',
    'delete-multiple-form' => '/admin/content/votes/delete-multiple',
  ],
  admin_permission: 'administer votes',
  collection_permission: 'administer votes',
  base_table: 'vote',
  label_count: [
    'singular' => '@count voto',
    'plural' => '@count votos',
  ],
  field_ui_base_route: 'entity.vote.settings',
  constraints: [
    'VoteAllowedAnswer' => [],
    'UniqueUserVote' => [],
  ],
)]
class Vote extends ContentEntityBase implements VoteInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (! $this->getOwnerId()) {
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    if ($this->id()) {
      return (string) new TranslatableMarkup('Voto #@id', ['@id' => $this->id()]);
    }

    return (string) new TranslatableMarkup('Novo voto');
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['voting'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Votação'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setSetting('target_type', 'voting')
      ->setSetting('handler', 'default:voting')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
        'settings' => ['link' => TRUE],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['selected_answer'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Resposta selecionada'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'answer' => 'answer',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
        'settings' => ['link' => FALSE],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Origem'))
      ->setSetting('max_length', 32)
      ->setDefaultValue('admin_ui');

    $fields['request_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Request ID'))
      ->setSetting('max_length', 128);

    $fields['idempotency_key'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Idempotency key'))
      ->setSetting('max_length', 128);

    $fields['idempotency_fingerprint'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Idempotency fingerprint'))
      ->setSetting('max_length', 64);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 10,
        'settings' => ['display_label' => TRUE],
      ])
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 10,
        'settings' => ['format' => 'enabled-disabled'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Usuário'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Registrado em'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'));

    return $fields;
  }

}
