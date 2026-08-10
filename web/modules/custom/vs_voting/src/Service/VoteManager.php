<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\vs_vote\VoteInterface;
use Drupal\vs_voting\Exception\DuplicateVoteException;
use Drupal\vs_voting\Exception\IdempotencyConflictException;
use Drupal\vs_voting\Exception\InvalidAnswerException;
use Drupal\vs_voting\Exception\InvalidJsonException;
use Drupal\vs_voting\Exception\VotingUnavailableException;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Observability\VotingTracer;
use Drupal\vs_voting\VotingInterface;
use Drupal\vs_voting\VotingParticipationHelper;
use Psr\Log\LoggerInterface;

/**
 * Shared vote creation service used by both UI and API.
 */
final readonly class VoteManager implements VoteManagerInterface {

  /**
   * VoteManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\vs_voting\VotingParticipationHelper $participationHelper
   *   The voting participation helper service.
   * @param \Drupal\vs_voting\Service\VotingRequestContext $requestContext
   *   The voting request context service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics service.
   * @param \Drupal\vs_voting\Observability\VotingTracer $tracer
   *   The voting tracer service.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private Connection                 $database,
    private CacheBackendInterface      $cacheBackend,
    private VotingParticipationHelper  $participationHelper,
    private VotingRequestContext       $requestContext,
    private LoggerInterface            $logger,
    private VotingMetrics              $metrics,
    private VotingTracer               $tracer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function castVote(
    VotingInterface $voting,
    int $answerId,
    AccountInterface $account,
    ?string $idempotencyKey = NULL,
    string $source = 'api'
  ): VoteInterface {
    return $this->tracer->trace('HTTP POST /votes', function () use (
      $voting,
      $answerId,
      $account,
      $idempotencyKey,
      $source
    ): VoteInterface {
      $uid = (int) $account->id();

      if (! $account->isAuthenticated() || $uid <= 0) {
        $this->metrics->increment('votes_rejected_total', ['reason' => 'unauthenticated', 'source' => $source]);
        throw new VotingUnavailableException('É necessário estar autenticado para votar.');
      }

      if (! $account->hasPermission('participate in voting')) {
        $this->metrics->increment('votes_rejected_total', ['reason' => 'missing_permission', 'source' => $source]);
        throw new VotingUnavailableException('Você não possui permissão para votar.');
      }

      if (! $this->participationHelper->hasValidConfiguration($voting)) {
        $this->metrics->increment('votes_rejected_total', ['reason' => 'invalid_configuration', 'source' => $source]);
        throw new VotingUnavailableException('Esta votação não está disponível para participação.');
      }

      if ($answerId <= 0 || !$this->participationHelper->isAllowedAnswer($voting, $answerId)) {
        $this->metrics->increment('votes_invalid_answer_total', ['source' => $source]);

        $this->logger->warning('Invalid answer rejected during vote creation.', [
          'request_id' => $this->requestContext->getRequestId(),
          'voting_id' => (int) $voting->id(),
          'uid' => $uid,
          'answer_id' => $answerId,
          'source' => $source,
        ]);

        throw new InvalidAnswerException('A resposta selecionada não pertence a esta votação.');
      }

      $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

      $fingerprint = $idempotencyKey ? $this->buildIdempotencyFingerprint((int) $voting->id(), $answerId) : NULL;

      if ($idempotencyKey) {
        $existing = $this->loadVoteByIdempotencyKey($uid, $idempotencyKey);

        if ($existing) {
          $existingFingerprint = (string) $existing->get('idempotency_fingerprint')->value;

          if ($existingFingerprint !== $fingerprint) {
            $this->metrics->increment('votes_failed_total', ['reason' => 'idempotency_conflict', 'source' => $source]);
            $this->metrics->increment('votes_idempotency_conflicts_total', ['source' => $source]);
            throw new IdempotencyConflictException('A mesma chave de idempotência foi reutilizada com outro payload.');
          }

          return $existing;
        }
      }

      if ($this->participationHelper->hasUserVoted($voting, $uid)) {
        $this->metrics->increment('votes_duplicate_total', ['source' => $source]);

        $this->logger->notice('Duplicate vote blocked before persistence.', [
          'request_id' => $this->requestContext->getRequestId(),
          'voting_id' => (int) $voting->id(),
          'uid' => $uid,
          'source' => $source,
        ]);

        throw new DuplicateVoteException('Você já participou desta votação.');
      }

      /** @var \Drupal\vs_vote\VoteInterface $vote */
      $vote = $this->entityTypeManager->getStorage('vote')->create([
        'voting' => $voting->id(),
        'selected_answer' => $answerId,
        'uid' => $uid,
        'status' => TRUE,
        'source' => $source,
        'request_id' => $this->requestContext->getRequestId(),
        'idempotency_key' => $idempotencyKey,
        'idempotency_fingerprint' => $fingerprint,
      ]);

      $violations = $vote->validate();

      if (count($violations) > 0) {
        foreach ($violations as $violation) {
          $message = (string) $violation->getMessage();

          if (str_contains($message, 'já participou')) {
            $this->metrics->increment('votes_duplicate_total', ['source' => $source]);
            throw new DuplicateVoteException('Você já participou desta votação.');
          }

          if (str_contains($message, 'não pertence')) {
            $this->metrics->increment('votes_invalid_answer_total', ['source' => $source]);
            throw new InvalidAnswerException('A resposta selecionada não pertence a esta votação.');
          }
        }

        $this->metrics->increment('votes_rejected_total', ['reason' => 'validation_failed', 'source' => $source]);
        throw new VotingUnavailableException('O voto não pôde ser validado.');
      }

      $start = microtime(TRUE);

      try {
        $vote->save();
      } catch (\Throwable $exception) {
        if ($idempotencyKey) {
          $existing = $this->loadVoteByIdempotencyKey($uid, $idempotencyKey);

          if ($existing) {
            $existingFingerprint = (string) $existing->get('idempotency_fingerprint')->value;

            if ($existingFingerprint === $fingerprint) {
              return $existing;
            }

            throw new IdempotencyConflictException('A mesma chave de idempotência foi reutilizada com outro payload.', 0, $exception);
          }
        }

        if ($this->participationHelper->hasUserVoted($voting, $uid)) {
          $this->metrics->increment('votes_duplicate_total', ['source' => $source]);

          throw new DuplicateVoteException('Você já participou desta votação.', 0, $exception);
        }

        $this->metrics->increment('votes_failed_total', ['reason' => 'persistence_error', 'source' => $source]);

        $this->logger->error('Vote persistence failed.', [
          'request_id' => $this->requestContext->getRequestId(),
          'voting_id' => (int) $voting->id(),
          'uid' => $uid,
          'answer_id' => $answerId,
          'source' => $source,
          'exception_class' => $exception::class,
        ]);

        throw $exception;
      }

      $this->metrics->increment('votes_created_total', ['source' => $source]);
      $this->metrics->observe('database_vote_insert_duration_seconds', microtime(TRUE) - $start, ['source' => $source]);
      $this->cacheBackend->delete('voting_results:' . $voting->id());

      $this->logger->info('Vote created successfully.', [
        'request_id' => $this->requestContext->getRequestId(),
        'vote_id' => (int) $vote->id(),
        'voting_id' => (int) $voting->id(),
        'uid' => $uid,
        'answer_id' => $answerId,
        'source' => $source,
      ]);

      return $vote;
    }, [
      'request_id' => $this->requestContext->getRequestId(),
      'voting_id' => (int) $voting->id(),
      'uid' => (int) $account->id(),
    ]);
  }

  /**
   * Loads a vote by idempotency key for the current user.
   *
   * @param int $uid
   *   The uid.
   * @param string $idempotencyKey
   *   The idempotency key.
   *
   * @return VoteInterface|null
   *   The vote, if found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadVoteByIdempotencyKey(int $uid, string $idempotencyKey): ?VoteInterface {
    $ids = $this->entityTypeManager->getStorage('vote')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('idempotency_key', $idempotencyKey)
      ->range(0, 1)
      ->execute();

    if ($ids === []) {
      return NULL;
    }

    $vote = $this->entityTypeManager->getStorage('vote')->load((int) reset($ids));

    return $vote instanceof VoteInterface ? $vote : NULL;
  }

  /**
   * Builds the idempotency fingerprint.
   *
   * @param int $votingId
   *   The voting id.
   * @param int $answerId
   *   The answer id.
   *
   * @return string
   *   The fingerprint.
   *
   * @throws \JsonException
   */
  private function buildIdempotencyFingerprint(int $votingId, int $answerId): string {
    return hash('sha256', json_encode([
      'voting_id' => $votingId,
      'answer_id' => $answerId,
    ], JSON_THROW_ON_ERROR));
  }

  /**
   * Normalizes the idempotency key.
   *
   * @param string|null $idempotencyKey
   *   The idempotency key.
   *
   * @return string|null
   *   The normalized idempotency key.
   */
  private function normalizeIdempotencyKey(?string $idempotencyKey): ?string {
    if ($idempotencyKey === NULL) {
      return NULL;
    }

    $idempotencyKey = trim($idempotencyKey);

    if ($idempotencyKey === '') {
      return NULL;
    }

    if (
      strlen($idempotencyKey) > 128 ||
      preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1) {
      throw new InvalidJsonException('O header Idempotency-Key é inválido.');
    }

    return $idempotencyKey;
  }

}
