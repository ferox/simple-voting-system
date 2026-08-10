<?php

declare(strict_types=1);

namespace Drupal\vs_voting_settings\Entity;

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
use Drupal\vs_voting_settings\Form\VotingSettingsForm;
use Drupal\vs_voting_settings\VotingSettingsAccessControlHandler;
use Drupal\vs_voting_settings\VotingSettingsInterface;
use Drupal\vs_voting_settings\VotingSettingsListBuilder;

/**
 * Provides a voting settings entity.
 *
 * @see \Drupal\vs_voting_settings\Entity\VotingSettings
 */
#[ContentEntityType(
  id: 'voting_settings',
  label: new TranslatableMarkup('Configuração da Votação'),
  label_collection: new TranslatableMarkup('Configuração das Votações'),
  label_singular: new TranslatableMarkup('configuração da votação'),
  label_plural: new TranslatableMarkup('configuração das votações'),
  entity_keys: [
    'id' => 'id',
    'langcode' => 'langcode',
    'label' => 'title',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => VotingSettingsListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => VotingSettingsAccessControlHandler::class,
    'form' => [
      'add' => VotingSettingsForm::class,
      'edit' => VotingSettingsForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/voting-settings',
    'add-form' => '/admin/content/voting-settings/add',
    'canonical' => '/admin/content/voting-settings/{voting_settings}',
    'edit-form' => '/admin/content/voting-settings/{voting_settings}/edit',
    'delete-form' => '/admin/content/voting-settings/{voting_settings}/delete',
    'delete-multiple-form' => '/admin/content/voting-settings/delete-multiple',
  ],
  admin_permission: 'administer voting_settings',
  base_table: 'voting_settings',
  data_table: 'voting_settings_field_data',
  translatable: TRUE,
  label_count: [
    'singular' => '@count configuração da votaçãos',
    'plural' => '@count configuração da votações',
  ],
  field_ui_base_route: 'entity.voting_settings.settings',
)]
class VotingSettings extends ContentEntityBase implements VotingSettingsInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (! $this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['question_answers'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setRevisionable(TRUE)
      ->setLabel(new TranslatableMarkup('Pergunta'))
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'target_bundles' => ['question' => 'question'],
        'negate' => 0,
      ])
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'paragraphs',
        'weight' => -4,
        'settings' => [
          'title' => t('Pergunta'),
          'add_mode' => 'dropdown',
          'closed_mode' => 'summary',
          'autocollapse' => 'none',
          'form_display_mode' => 'default',
          'default_paragraph_type' => 'question',
          'edit_mode' => 'open',
          'features' => [
            'duplicate' => 'duplicate',
            'collapse_edit_all' => 'collapse_edit_all',
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_revisions_entity_view',
        'weight' => -4,
        'settings' => [
          'view_mode' => 'default',
          'link' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['redirect_to_results'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Redirecionar para resultados após votar'))
      ->setDescription(new TranslatableMarkup(
        'Quando habilitado, participantes serão redirecionados para listagem de resultados.'
      ))
      ->setDefaultValue(FALSE)
      ->setSetting('on_label', 'Enabled')
      ->setSetting('off_label', 'Disabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => -3,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);


    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE);

    return $fields;
  }

}
