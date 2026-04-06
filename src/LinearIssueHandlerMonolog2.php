<?php

namespace SamirMhsnv\LaravelLinearIssues;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Throwable;

class LinearIssueHandlerMonolog2 extends AbstractProcessingHandler
{
    private const LINEAR_GRAPHQL_ENDPOINT = 'https://api.linear.app/graphql';

    public function __construct(
        private readonly array $config = [],
        int $level = Logger::ERROR,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function write($record): void
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        $teamId = (string) ($this->config['team_id'] ?? '');

        if ($apiKey === '' || $teamId === '') {
            return;
        }

        $payload = [
            'query' => <<<'GRAPHQL'
mutation IssueCreate($input: IssueCreateInput!) {
  issueCreate(input: $input) {
    success
  }
}
GRAPHQL,
            'variables' => [
                'input' => array_filter([
                    'teamId' => $teamId,
                    'title' => $this->buildTitle($record),
                    'description' => $this->buildDescription($record),
                    'priority' => $this->resolvePriority(),
                    'projectId' => $this->config['project_id'] ?? null,
                    'stateId' => $this->config['state_id'] ?? null,
                    'labelIds' => $this->resolveLabelIds((string) ($record['level_name'] ?? 'ERROR')),
                ], static fn ($value): bool => $value !== null),
            ],
        ];

        try {
            $client = new Client([
                'base_uri' => self::LINEAR_GRAPHQL_ENDPOINT,
                'timeout' => (float) ($this->config['timeout'] ?? 3.0),
                'connect_timeout' => (float) ($this->config['connect_timeout'] ?? 1.5),
            ]);

            $response = $client->post('', [
                'headers' => [
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $decoded = json_decode((string) $response->getBody(), true);

            if (is_array($decoded) && isset($decoded['errors'])) {
                error_log('laravel-linear-issues: Linear API returned errors '.json_encode($decoded['errors']));
            }
        } catch (GuzzleException) {
            // Swallow exceptions to avoid recursive logging failures.
        }
    }

    private function resolveLabelIds(string $levelName): ?array
    {
        if (! $this->shouldApplyBugLabel($levelName)) {
            return null;
        }

        $bugLabelId = $this->config['bug_label_id'] ?? null;

        if (! is_string($bugLabelId)) {
            return null;
        }

        $bugLabelId = trim($bugLabelId);

        return $bugLabelId === '' ? null : [$bugLabelId];
    }

    private function resolvePriority(): ?int
    {
        $priority = $this->config['priority'] ?? null;

        if ($priority === null || $priority === '') {
            return null;
        }

        return is_numeric($priority) ? (int) $priority : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function buildTitle(array $record): string
    {
        $prefix = (string) ($this->config['title_prefix'] ?? config('app.name', 'Laravel'));
        $prefix = trim($prefix);
        $message = mb_substr((string) ($record['message'] ?? ''), 0, 180);

        return $prefix === '' ? $message : sprintf('[%s] %s', $prefix, $message);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function buildDescription(array $record): string
    {
        $contextData = $record['context'] ?? [];
        $extraData = $record['extra'] ?? [];
        $context = is_array($contextData) && $contextData !== [] ? json_encode($contextData, JSON_PRETTY_PRINT) : '{}';
        $extra = is_array($extraData) && $extraData !== [] ? json_encode($extraData, JSON_PRETTY_PRINT) : '{}';
        $exceptionDetails = $this->buildExceptionDetails($contextData);

        $sections = [
            '### Message',
            (string) ($record['message'] ?? ''),
            '### Datetime',
            isset($record['datetime']) && $record['datetime'] instanceof \DateTimeInterface
                ? $record['datetime']->format(DATE_ATOM)
                : now()->toAtomString(),
        ];

        if ($exceptionDetails !== null) {
            $sections[] = '### Exception';
            $sections[] = $exceptionDetails;
        }

        return implode("\n\n", [
            ...$sections,
            '### Context',
            '```json'."\n".$context."\n".'```',
            '### Extra',
            '```json'."\n".$extra."\n".'```',
        ]);
    }

    private function buildExceptionDetails(mixed $contextData): ?string
    {
        if (! is_array($contextData)) {
            return null;
        }

        $exception = $contextData['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return null;
        }

        return implode("\n\n", [
            sprintf('`%s`', $exception::class),
            sprintf('File: `%s:%d`', $exception->getFile(), $exception->getLine()),
            '```',
            $exception->getTraceAsString(),
            '```',
        ]);
    }

    private function shouldApplyBugLabel(string $levelName): bool
    {
        return match (strtoupper($levelName)) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => true,
            default => false,
        };
    }
}
