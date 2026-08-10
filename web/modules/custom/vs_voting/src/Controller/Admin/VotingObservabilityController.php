<?php

declare(strict_types=1);

namespace Drupal\vs_voting\Controller\Admin;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\vs_voting\Observability\VotingMetrics;
use Drupal\vs_voting\Observability\VotingTracer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders an observability dashboard for the voting API.
 */
final class VotingObservabilityController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The number of items per page.
   */
  private const int PAGE_SIZE = 20;

  /**
   * The number used to paginate the list of counters.
   */
  private const int COUNTERS_PAGER = 0;

  /**
   * The number used to paginate the list of histograms.
   */
  private const int HISTOGRAMS_PAGER = 1;

  /**
   * The number used to paginate the list of spans.
   */
  private const int SPANS_PAGER = 2;

  /**
   * VotingObservabilityController constructor.
   *
   * @param \Drupal\vs_voting\Observability\VotingMetrics $metrics
   *   The voting metrics service.
   * @param \Drupal\vs_voting\Observability\VotingTracer $tracer
   *   The voting tracer service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The pager manager service.
   */
  public function __construct(
    private VotingMetrics $metrics,
    private VotingTracer $tracer,
    private DateFormatterInterface $dateFormatter,
    private PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static(
      $container->get('vs_voting.metrics'),
      $container->get('vs_voting.tracer'),
      $container->get('date.formatter'),
      $container->get('pager.manager'),
    );
    $instance->setStringTranslation($container->get('string_translation'));

    return $instance;
  }

  /**
   * Displays aggregated metrics and recent spans.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  public function dashboard(): array {
    $summary_counters = $this->metrics->getCounterSummaries(NULL);

    return [
      '#cache' => ['max-age' => 0],
      'summary' => $this->buildSummary($summary_counters),
      'counters' => $this->buildCountersSection(),
      'histograms' => $this->buildHistogramsSection(),
      'spans' => $this->buildSpansSection(),
    ];
  }

  /**
   * Builds the top-line summary.
   *
   * @param array<int, array<string, mixed>> $counters
   *   Counter rows.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  private function buildSummary(array $counters): array {
    $snapshot_metrics = [
      'voting_api_requests_total',
      'voting_api_success_total',
      'votes_created_total',
      'voting_api_errors_total',
      'oauth_authentication_failures_total',
      'voting_trace_spans_total',
    ];

    $items = [];

    foreach ($snapshot_metrics as $metric_name) {
      $items[] = $this->metricLabel($metric_name) . ': ' . $this->sumMetric($counters, $metric_name);
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Resumo'),
      '#open' => TRUE,
      'items' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Builds the counter metrics table.
   *
   * @param array<int, array<string, mixed>> $counters
   *   Counter rows.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  private function buildCountersTable(array $counters): array {
    $rows = [];

    foreach ($counters as $counter) {
      $rows[] = [
        'metric_name' => $this->metricLabel((string) $counter['metric_name']),
        'labels' => $this->formatLabels($counter['labels']),
        'value' => (string) $counter['counter_value'],
        'updated' => $this->formatTimestamp((int) $counter['changed']),
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Métricas de contagem'),
      '#header' => [
        $this->t('Métrica'),
        $this->t('Detalhes'),
        $this->t('Valor'),
        $this->t('Atualizado em'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhuma métrica de contagem foi registrada ainda.'),
    ];
  }

  /**
   * Builds the paginated counter section.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  private function buildCountersSection(): array {
    $total = $this->metrics->countCounterSummaries();
    $pager = $this->pagerManager->createPager($total, self::PAGE_SIZE, self::COUNTERS_PAGER);
    $offset = $pager->getCurrentPage() * self::PAGE_SIZE;
    $counters = $this->metrics->getCounterSummaries(self::PAGE_SIZE, $offset);

    return [
      'table' => $this->buildCountersTable($counters),
      'pager' => [
        '#type' => 'pager',
        '#element' => self::COUNTERS_PAGER,
        '#quantity' => 5,
      ],
    ];
  }

  /**
   * Builds the histogram metrics table.
   *
   * @param array<int, array<string, mixed>> $histograms
   *   Histogram rows.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  private function buildHistogramsTable(array $histograms): array {
    $rows = [];

    foreach ($histograms as $histogram) {
      $average = (int) $histogram['sample_count'] > 0
        ? (float) $histogram['sample_sum'] / (int) $histogram['sample_count']
        : 0.0;

      $rows[] = [
        'metric_name' => $this->metricLabel((string) $histogram['metric_name']),
        'labels' => $this->formatLabels($histogram['labels']),
        'sample_count' => (string) $histogram['sample_count'],
        'average' => number_format($average, 4, '.', ''),
        'min' => $histogram['sample_min'] !== NULL ? number_format((float) $histogram['sample_min'], 4, '.', '') : '0.0000',
        'max' => $histogram['sample_max'] !== NULL ? number_format((float) $histogram['sample_max'], 4, '.', '') : '0.0000',
        'last' => $histogram['last_value'] !== NULL ? number_format((float) $histogram['last_value'], 4, '.', '') : '0.0000',
        'updated' => $this->formatTimestamp((int) $histogram['changed']),
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Métricas de distribuição'),
      '#header' => [
        $this->t('Métrica'),
        $this->t('Detalhes'),
        $this->t('Amostras'),
        $this->t('Média'),
        $this->t('Mínimo'),
        $this->t('Máximo'),
        $this->t('Último valor'),
        $this->t('Atualizado em'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhuma métrica de distribuição foi registrada ainda.'),
    ];
  }

  /**
   * Builds the paginated histogram section.
   *
   * @return array<string, mixed>
   *   The render array.
   * @throws \JsonException
   */
  private function buildHistogramsSection(): array {
    $total = $this->metrics->countHistogramSummaries();
    $pager = $this->pagerManager->createPager($total, self::PAGE_SIZE, self::HISTOGRAMS_PAGER);
    $offset = $pager->getCurrentPage() * self::PAGE_SIZE;
    $histograms = $this->metrics->getHistogramSummaries(self::PAGE_SIZE, $offset);

    return [
      'table' => $this->buildHistogramsTable($histograms),
      'pager' => [
        '#type' => 'pager',
        '#element' => self::HISTOGRAMS_PAGER,
        '#quantity' => 5,
      ],
    ];
  }

  /**
   * Builds the recent spans table.
   *
   * @param array<int, array<string, mixed>> $spans
   *   Recent spans.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  private function buildSpansTable(array $spans): array {
    $rows = [];

    foreach ($spans as $span) {
      $rows[] = [
        'created' => $this->formatTimestamp((int) $span['created']),
        'span_name' => $this->formatSpanName((string) $span['span_name']),
        'status' => $this->translateLabelValue('status', $span['status']),
        'duration_ms' => number_format((float) $span['duration_ms'], 3, '.', ''),
        'request_id' => $span['request_id'] !== '' ? $span['request_id'] : '-',
        'context' => $this->formatLabels($span['context']),
        'exception' => $span['exception_class'] !== NULL
          ? $span['exception_class'] . ': ' . (string) $span['exception_message']
          : '-',
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Rastros recentes'),
      '#header' => [
        $this->t('Registrado em'),
        $this->t('Operação'),
        $this->t('Status'),
        $this->t('Duração (ms)'),
        $this->t('ID da requisição'),
        $this->t('Contexto'),
        $this->t('Exceção'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum rastro foi registrado ainda.'),
    ];
  }

  /**
   * Builds the paginated span section.
   *
   * @return array<string, mixed>
   *   The render array.
   *
   * @throws \JsonException
   */
  private function buildSpansSection(): array {
    $total = $this->tracer->countRecentSpans();
    $pager = $this->pagerManager->createPager($total, self::PAGE_SIZE, self::SPANS_PAGER);
    $offset = $pager->getCurrentPage() * self::PAGE_SIZE;
    $spans = $this->tracer->getRecentSpans(self::PAGE_SIZE, $offset);

    return [
      'table' => $this->buildSpansTable($spans),
      'pager' => [
        '#type' => 'pager',
        '#element' => self::SPANS_PAGER,
        '#quantity' => 5,
      ],
    ];
  }

  /**
   * Formats an associative array as a short label string.
   *
   * @param array<string, mixed> $labels
   *   Labels or context values.
   *
   * @throws \JsonException
   */
  private function formatLabels(array $labels): string {
    if ($labels === []) {
      return '-';
    }

    $items = [];

    foreach ($labels as $key => $value) {
      if (is_array($value)) {
        $value = json_encode($value, JSON_THROW_ON_ERROR);
      }
      elseif (is_bool($value)) {
        $value = $value ? 'true' : 'false';
      }
      elseif ($value === NULL) {
        $value = 'null';
      }

      $items[] = $this->labelKeyLabel($key) . ': ' . $this->translateLabelValue($key, $value);
    }

    return implode(', ', $items);
  }

  /**
   * Formats a timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return string
   *   The formatted timestamp.
   */
  private function formatTimestamp(int $timestamp): string {
    return $timestamp > 0
      ? $this->dateFormatter->format($timestamp, 'short')
      : '-';
  }

  /**
   * Sums the metric value.
   *
   * @param array $counters
   *   A list of counters.
   * @param string $metricName
   *   The metric name.
   *
   * @return int
   *   The sum of the metric.
   */
  private function sumMetric(array $counters, string $metricName): int {
    $total = 0;
    foreach ($counters as $counter) {
      if ($counter['metric_name'] === $metricName) {
        $total += (int) $counter['counter_value'];
      }
    }

    return $total;
  }

  /**
   * Gets the metric label from a name.
   *
   * @param string $metricName
   *   The metric name.
   *
   * @return string
   *   The metric label.
   */
  private function metricLabel(string $metricName): string {
    return match ($metricName) {
      'voting_api_requests_total' => (string) $this->t('Total de requisições da API'),
      'voting_api_success_total' => (string) $this->t('Requisições da API concluídas com sucesso'),
      'votes_created_total' => (string) $this->t('Votos registrados com sucesso'),
      'voting_api_errors_total' => (string) $this->t('Erros retornados pela API'),
      'oauth_authentication_failures_total' => (string) $this->t('Falhas de autenticação OAuth'),
      'voting_trace_spans_total' => (string) $this->t('Rastros registrados'),
      'voting_trace_duration_seconds' => (string) $this->t('Duração dos rastros'),
      'voting_trace_failures_total' => (string) $this->t('Falhas nos rastros'),
      'voting_results_requests_total' => (string) $this->t('Consultas de resultados'),
      'voting_results_cache_hits_total' => (string) $this->t('Resultados servidos do cache'),
      'voting_results_cache_misses_total' => (string) $this->t('Resultados recalculados'),
      'voting_results_rejected_total' => (string) $this->t('Resultados indisponíveis'),
      'voting_results_query_duration_seconds' => (string) $this->t('Tempo de consulta dos resultados'),
      'voting_results_total_votes' => (string) $this->t('Total de votos por apuração'),
      'votes_invalid_answer_total' => (string) $this->t('Tentativas com resposta inválida'),
      'votes_failed_total' => (string) $this->t('Falhas ao registrar votos'),
      'votes_duplicate_total' => (string) $this->t('Tentativas de voto duplicado'),
      'votes_rejected_total' => (string) $this->t('Votos rejeitados'),
      'votes_idempotency_conflicts_total' => (string) $this->t('Conflitos de idempotência'),
      'database_vote_insert_duration_seconds' => (string) $this->t('Tempo de gravação do voto no banco'),
      'healthcheck_requests_total' => (string) $this->t('Execuções das verificações de saúde'),
      'healthcheck_failures_total' => (string) $this->t('Falhas nas verificações de saúde'),
      'healthcheck_request_duration_seconds' => (string) $this->t('Tempo das verificações de saúde'),
      default => $this->humanizeIdentifier($metricName),
    };
  }

  /**
   * Returns the label for the given healthcheck metric name.
   *
   * @param string $metricName
   *   The name of the metric.
   *
   * @return string
   *   The label for the metric.
   */
  private function labelKeyLabel(string $key): string
  {
    return match ($key) {
      'endpoint' => (string)$this->t('Endpoint'),
      'method' => (string)$this->t('Método'),
      'source' => (string)$this->t('Origem'),
      'reason' => (string)$this->t('Motivo'),
      'status' => (string)$this->t('Status'),
      'probe' => (string)$this->t('Verificação'),
      'code' => (string)$this->t('Código'),
      'span' => (string)$this->t('Operação'),
      'exception' => (string)$this->t('Exceção'),
      'request_id' => (string)$this->t('ID da requisição'),
      'voting_id' => (string)$this->t('ID da votação'),
      'uid' => (string)$this->t('ID do usuário'),
      default => $this->humanizeIdentifier($key),
    };
  }

  /**
   * Translates the label.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The translated label.
   *
   * @throws \JsonException
   */
  private function translateLabelValue(string $key, mixed $value): string {
    if (is_array($value)) {
      return json_encode($value, JSON_THROW_ON_ERROR);
    }

    if (is_bool($value)) {
      return $value ? (string) $this->t('Sim') : (string) $this->t('Não');
    }

    if ($value === NULL) {
      return (string) $this->t('Não informado');
    }

    $value = (string) $value;

    return match ($key . ':' . $value) {
      'endpoint:voting_collection' => (string) $this->t('Listagem de votações'),
      'endpoint:voting_detail' => (string) $this->t('Detalhes da votação'),
      'endpoint:vote_create' => (string) $this->t('Registro de voto'),
      'endpoint:voting_results' => (string) $this->t('Resultados da votação'),
      'endpoint:health_live' => (string) $this->t('Verificação de vida'),
      'endpoint:health_ready' => (string) $this->t('Verificação de prontidão'),
      'endpoint:unknown' => (string) $this->t('Desconhecido'),
      'method:GET' => 'GET',
      'method:POST' => 'POST',
      'source:api' => (string) $this->t('API'),
      'source:admin_ui' => (string) $this->t('Interface administrativa'),
      'status:ok' => (string) $this->t('Sucesso'),
      'status:error' => (string) $this->t('Erro'),
      'status:failed' => (string) $this->t('Falha'),
      'probe:live' => (string) $this->t('Vida'),
      'probe:ready' => (string) $this->t('Prontidão'),
      'reason:unauthenticated' => (string) $this->t('Usuário não autenticado'),
      'reason:missing_permission' => (string) $this->t('Permissão ausente'),
      'reason:invalid_configuration' => (string) $this->t('Configuração inválida'),
      'reason:validation_failed' => (string) $this->t('Falha de validação'),
      'reason:persistence_error' => (string) $this->t('Erro ao persistir no banco'),
      'reason:idempotency_conflict' => (string) $this->t('Conflito de idempotência'),
      'reason:missing_question_or_answers' => (string) $this->t('Pergunta ou respostas ausentes'),
      'code:already_voted' => (string) $this->t('Voto duplicado'),
      'code:idempotency_conflict' => (string) $this->t('Conflito de idempotência'),
      'code:invalid_answer' => (string) $this->t('Resposta inválida'),
      'code:invalid_json' => (string) $this->t('JSON inválido'),
      'code:voting_unavailable' => (string) $this->t('Votação indisponível'),
      'code:unauthenticated' => (string) $this->t('Não autenticado'),
      'code:access_denied' => (string) $this->t('Acesso negado'),
      'code:not_found' => (string) $this->t('Não encontrado'),
      'code:dependency_unavailable' => (string) $this->t('Dependência indisponível'),
      'code:internal_error' => (string) $this->t('Erro interno'),
      default => $value,
    };
  }

  /**
   * Formats the span name.
   *
   * @param string $spanName
   *   The span name.
   *
   * @return string
   *   The formatted span name.
   */
  private function formatSpanName(string $spanName): string {
    return match ($spanName) {
      'HTTP POST /votes' => (string) $this->t('Criação de voto'),
      default => $spanName,
    };
  }

  /**
   * Generates a human-readable identifier.
   *
   * @param string $value
   *  The value.
   *
   * @return string
   *   The human-readable identifier.
   */
  private function humanizeIdentifier(string $value): string {
    return ucfirst(str_replace('_', ' ', $value));
  }

}
