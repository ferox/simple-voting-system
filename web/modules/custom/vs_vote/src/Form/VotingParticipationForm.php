<?php

declare(strict_types=1);

namespace Drupal\vs_vote\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_voting\Exception\DuplicateVoteException;
use Drupal\vs_voting\Exception\IdempotencyConflictException;
use Drupal\vs_voting\Exception\InvalidAnswerException;
use Drupal\vs_voting\Exception\VotingUnavailableException;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\Service\VoteManagerInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative participation form that records a vote.
 */
final class VotingParticipationForm extends ContentEntityForm {

  /**
   * VotingParticipationForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $voteEntityTypeManager
   *   The vote entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $votingRouteMatch
   *   The voting route match service.
   * @param \Drupal\Core\Session\AccountProxyInterface $voteCurrentAccount
   *   The vote current account service.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper service.
   * @param \Drupal\vs_voting\Service\VoteManagerInterface $voteManager
   *   The vote manager service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    private readonly EntityTypeManagerInterface $voteEntityTypeManager,
    private readonly RouteMatchInterface $votingRouteMatch,
    private readonly AccountProxyInterface $voteCurrentAccount,
    private readonly VotingParticipationHelper $participationHelper,
    private readonly VoteManagerInterface $voteManager,
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
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('vs_voting.participation_helper'),
      $container->get('vs_voting.vote_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $voting = $this->getVotingFromRoute();

    if (! $voting) {
      $this->messenger()->addError($this->t('A votação não foi encontrada.'));
      unset($form['actions']['submit']);
      return $form;
    }

    $question = $this->participationHelper->getQuestion($voting);

    $answers = $this->participationHelper->getAnswers($voting);

    if (! $question || $answers === []) {
      $this->messenger()->addError(
        $this->t('Esta votação não possui uma configuração válida para participação.')
      );

      unset($form['actions']['submit']);

      return $form;
    }

    $form['#attached']['library'][] = 'vs_voting/voting_participation';

    foreach (['uid', 'status', 'voting', 'selected_answer'] as $field_name) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }

    $selected_answer_id = $this->entity->hasField('selected_answer')
      ? $this->entity->get('selected_answer')->target_id
      : NULL;

    $form['voting_information'] = [
      '#type' => 'container',
      '#weight' => -20,
    ];

    $form['voting_information']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $voting->label(),
      '#attributes' => ['class' => ['vs-voting-participation__title']],
    ];

    $form['question'] = [
      '#type' => 'fieldset',
      '#title' => $this->getQuestionTitle($question),
      '#weight' => -10,
      '#after_build' => [[static::class, 'buildAnswerCards']],
      '#answer_card_data' => [],
    ];

    $form['question']['selected_answer'] = [
      '#type' => 'radios',
      '#title' => $this->t('Selecione uma resposta'),
      '#title_display' => 'invisible',
      '#default_value' => $selected_answer_id,
      '#options' => [],
      '#parents' => ['selected_answer'],
    ];

    $form['question']['answers'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['voting-answer-list'],
      ],
    ];

    foreach ($answers as $answer_id => $answer) {
      $form['question']['selected_answer']['#options'][$answer_id] = $this->getAnswerTitle($answer);

      $form['question']['#answer_card_data'][$answer_id] = [
        'title' => $this->getAnswerTitle($answer),
        'description' => $this->buildAnswerDescription($answer),
        'image' => $this->buildAnswerImage($answer),
      ];
    }

    $form['actions']['#weight'] = 100;

    $form['actions']['submit']['#value'] = $this->t('Registrar voto');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state): EntityInterface|VoteInterface {
    /** @var \Drupal\vs_vote\VoteInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $voting = $this->getVotingFromRoute();

    $selected_answer_id = $form_state->getValue('selected_answer');

    if ($voting) {
      $entity->set('voting', $voting->id());
    }

    if ($selected_answer_id) {
      $entity->set('selected_answer', $selected_answer_id);
    }

    $entity->setOwnerId($this->voteCurrentAccount->id());

    $entity->set('status', TRUE);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $voting = $this->getVotingFromRoute();

    $selected_answer_id = $form_state->getValue('selected_answer');

    if (! $voting) {
      $form_state->setError($form, $this->t('A votação não foi encontrada.'));

      return;
    }

    if (! $selected_answer_id) {
      $form_state->setErrorByName('selected_answer', $this->t('Selecione uma resposta.'));

      return;
    }

    if (! $this->participationHelper->isAllowedAnswer($voting, (string) $selected_answer_id)) {
      $form_state->setErrorByName('selected_answer', $this->t('A resposta selecionada não pertence a esta votação.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $voting = $this->getVotingFromRoute();

    $selected_answer_id = (int) $form_state->getValue('selected_answer');

    if (! $voting || $selected_answer_id <= 0) {
      $this->messenger()->addError($this->t('Não foi possível registrar o voto.'));

      return 0;
    }

    try {
      $this->voteManager->castVote(
        $voting,
        $selected_answer_id,
        $this->voteCurrentAccount,
        NULL,
        'admin_ui',
      );
    }
    catch (
      DuplicateVoteException |
      IdempotencyConflictException |
      InvalidAnswerException |
      VotingUnavailableException $exception
    ) {
      $this->messenger()->addError($exception->getMessage());

      return 0;
    }

    $this->messenger()->addStatus($this->t('Seu voto foi registrado com sucesso.'));

    $redirect_route = 'view.available_votings.page_1';

    $settings = $voting ? $this->participationHelper->getVotingSettings($voting) : NULL;

    if (
      $settings &&
      $settings->hasField('redirect_to_results') &&
      $settings->get('redirect_to_results')->value) {
      $redirect_route = 'view.voting_results.page_1';
    }

    $form_state->setRedirect($redirect_route);

    return SAVED_NEW;
  }

  /**
   * Retrieves the voting entity from the route.
   *
   * @return \Drupal\vs_voting\VotingInterface|null
   *   The voting entity, or NULL if it cannot be retrieved.
   */
  private function getVotingFromRoute(): ?VotingInterface {
    $voting = $this->votingRouteMatch->getParameter('voting');

    return $voting instanceof VotingInterface ? $voting : NULL;
  }

  /**
   * Builds the answer cards.
   *
   * @param array $element
   *  The form element.
   * @param FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The form element with the answer cards.
   */
  public static function buildAnswerCards(array $element, FormStateInterface $form_state): array {
    $answer_card_data = $element['#answer_card_data'] ?? [];

    foreach ($answer_card_data as $answer_id => $card_data) {
      if (! isset($element['selected_answer'][$answer_id])) {
        continue;
      }

      $element['selected_answer'][$answer_id]['#id'] = 'voting-answer-' . $answer_id;
      $element['selected_answer'][$answer_id]['#title_display'] = 'invisible';

      if ($card_data['description'] !== []) {
        $element['selected_answer'][$answer_id]['#attributes']['aria-describedby'] = 'voting-answer-description-' . $answer_id;
      }

      $element['answers'][$answer_id] = [
        '#theme' => 'voting_answer_card',
        '#answer_id' => (string) $answer_id,
        '#title' => $card_data['title'],
        '#description' => $card_data['description'],
        '#image' => $card_data['image'],
        '#radio' => $element['selected_answer'][$answer_id],
      ];

      unset($element['selected_answer'][$answer_id]);
    }

    return $element;
  }

  /**
   * Returns the answer title string.
   *
   * @param ParagraphInterface $answer
   *   The paragraph type for answer.
   *
   * @return string
   *   The answer title.
   */
  private function getAnswerTitle(ParagraphInterface $answer): string {
    return $answer->hasField('field_answer') ? (string) $answer->get('field_answer')->value : '';
  }

  /**
   * Builds the answer description render array.
   */
  private function buildAnswerDescription(ParagraphInterface $answer): array {
    if ($answer->hasField('field_description') && !$answer->get('field_description')->isEmpty()) {
      return $answer->get('field_description')->view(['label' => 'hidden']);
    }

    return [];
  }

  /**
   * Builds the answer image render array.
   *
   * @param ParagraphInterface $answer
   *   The paragraph type for answer.
   *
   * @return array
   *   The answer image render array.
   */
  private function buildAnswerImage(ParagraphInterface $answer): array {
    if (! $answer->hasField('field_image') || $answer->get('field_image')->isEmpty()) {
      return [];
    }

    $media = $answer->get('field_image')->entity;

    if (!$media) {
      return [];
    }

    return $this->voteEntityTypeManager
      ->getViewBuilder('media')
      ->view($media, 'voting_answer');
  }

  /**
   * Returns the configured question title.
   *
   * @param ParagraphInterface $question
   *   The paragraph type for question.
   *
   * @return string
   *   The question title.
   */
  private function getQuestionTitle(ParagraphInterface $question): string {
    if ($question->hasField('field_question') && ! $question->get('field_question')->isEmpty()) {
      return (string) $question->get('field_question')->value;
    }

    return (string) $this->t('Pergunta');
  }

}
