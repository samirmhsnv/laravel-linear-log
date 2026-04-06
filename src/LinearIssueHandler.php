<?php

namespace SamirMhsnv\LaravelLinearIssues;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class LinearIssueHandler extends AbstractProcessingHandler
{
    private const LINEAR_GRAPHQL_ENDPOINT = 'https://api.linear.app/graphql';

    public function __construct(
        private readonly array $config = [],
        int|string|Level $level = Level::Error,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
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
                    'labelIds' => $this->resolveLabelIds($record->level->getName()),
                ], fn (mixed $value): bool => $value !== null),
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

    private function buildTitle(LogRecord $record): string
    {
        $prefix = (string) ($this->config['title_prefix'] ?? config('app.name', 'Laravel'));
        $prefix = trim($prefix);
        $message = mb_substr((string) $record->message, 0, 180);

        return $prefix === '' ? $message : sprintf('[%s] %s', $prefix, $message);
    }

    private function buildDescription(LogRecord $record): string
    {
        $context = $record->context === [] ? '{}' : json_encode($record->context, JSON_PRETTY_PRINT);
        $extra = $record->extra === [] ? '{}' : json_encode($record->extra, JSON_PRETTY_PRINT);
        $exceptionDetails = $this->buildExceptionDetails($record->context);

        $sections = [
            '### Message',
            (string) $record->message,
            '### Datetime',
            $record->datetime->format(DATE_ATOM),
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildExceptionDetails(array $context): ?string
    {
        $exception = $context['exception'] ?? null;

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
