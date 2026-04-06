# Laravel Linear Issues

Create Linear issues automatically from Laravel log events using a custom Monolog logging driver.

## Installation

```bash
composer require samirmhsnv/laravel-linear-log
```

Laravel package auto-discovery registers the service provider automatically.

Publish package config (optional, if you want a dedicated config file):

```bash
php artisan vendor:publish --tag=linear-issues-config
```

## Configuration

The package registers a custom log driver named `linear`.

Example channel in your app `config/logging.php`:

```php
'linear' => [
    'driver' => 'linear',
    'level' => env('LINEAR_LOG_LEVEL', 'error'),
    'api_key' => env('LINEAR_API_KEY'),
    'team_id' => env('LINEAR_TEAM_ID'),
    'project_id' => env('LINEAR_PROJECT_ID'),
    'state_id' => env('LINEAR_STATE_ID'),
    'bug_label_id' => env('LINEAR_BUG_LABEL_ID'),
    'priority' => env('LINEAR_PRIORITY'),
    'title_prefix' => env('LINEAR_TITLE_PREFIX', env('APP_NAME', 'Laravel')),
    'timeout' => env('LINEAR_TIMEOUT', 3.0),
    'connect_timeout' => env('LINEAR_CONNECT_TIMEOUT', 1.5),
],
```

## Environment Variables

| Key | Required | Description | Example |
| --- | --- | --- | --- |
| `LINEAR_LOG_LEVEL` | No | Minimum log level sent to the `linear` channel. Use `error` for production issue creation. | `error` |
| `LINEAR_API_KEY` | Yes | Linear API key used in the `Authorization` header for GraphQL requests. | `lin_api_xxxxxxxxxxxxx` |
| `LINEAR_TEAM_ID` | Yes | Linear team ID where issues are created. | `a1b2c3d4-e5f6-7890-abcd-ef1234567890` |
| `LINEAR_PROJECT_ID` | No | Optional Linear project ID to attach created issues to a specific project. | `1f2e3d4c-5b6a-7890-cdef-1234567890ab` |
| `LINEAR_STATE_ID` | No | Optional workflow state ID (e.g. Backlog, Todo). If omitted, Linear uses the team's default initial state. | `9a8b7c6d-5e4f-3210-abcd-1234567890ef` |
| `LINEAR_BUG_LABEL_ID` | No | Linear label ID for the `Bug` badge. Applied automatically for `error`/`critical` logs only. | `c0ffee00-1111-2222-3333-444455556666` |
| `LINEAR_PRIORITY` | No | Optional Linear priority value for created issues. | `2` |
| `LINEAR_TITLE_PREFIX` | No | Prefix added to issue titles before log details. | `Chatasist` |
| `LINEAR_TIMEOUT` | No | HTTP request timeout in seconds for the Linear API request. | `3.0` |
| `LINEAR_CONNECT_TIMEOUT` | No | HTTP connect timeout in seconds for establishing the Linear API connection. | `1.5` |

## Usage

### Send logs directly to Linear

```php
use Illuminate\Support\Facades\Log;

Log::channel('linear')->error('Payment webhook failed', [
    'order_id' => 1234,
    'provider' => 'stripe',
]);
```

### Add to stack channel

```dotenv
LOG_STACK=single,linear
```

When used in a stack, Laravel will continue writing to your standard channel(s) while also creating Linear issues for matching log levels.

## Setup IDs From Linear API

Set your API key first:

```dotenv
LINEAR_API_KEY=lin_api_xxxxxxxxxxxxx
```

Then fetch IDs using these commands.

### Get Team ID

```bash
php artisan tinker --execute '$apiKey = env("LINEAR_API_KEY"); $query = "query { teams { nodes { id key name } } }"; $response = Illuminate\Support\Facades\Http::withHeaders(["Authorization" => $apiKey, "Content-Type" => "application/json"])->post("https://api.linear.app/graphql", ["query" => $query]); dump($response->json());'
```

Pick your team from `key` / `name`, then set:

```dotenv
LINEAR_TEAM_ID=<team-uuid>
```

### Get Project ID (Optional)

```bash
php artisan tinker --execute '$apiKey = env("LINEAR_API_KEY"); $query = "query { projects { nodes { id name } } }"; $response = Illuminate\Support\Facades\Http::withHeaders(["Authorization" => $apiKey, "Content-Type" => "application/json"])->post("https://api.linear.app/graphql", ["query" => $query]); dump($response->json());'
```

Set:

```dotenv
LINEAR_PROJECT_ID=<project-uuid>
```

### Get State ID (Optional)

```bash
php artisan tinker --execute '$apiKey = env("LINEAR_API_KEY"); $teamId = env("LINEAR_TEAM_ID"); $query = "query { team(id: \"".$teamId."\") { states { nodes { id name type } } } }"; $response = Illuminate\Support\Facades\Http::withHeaders(["Authorization" => $apiKey, "Content-Type" => "application/json"])->post("https://api.linear.app/graphql", ["query" => $query]); dump($response->json());'
```

Set:

```dotenv
LINEAR_STATE_ID=<state-uuid>
```

### Get Bug Label ID (Optional)

```bash
php artisan tinker --execute '$apiKey = env("LINEAR_API_KEY"); $query = "query { issueLabels { nodes { id name } } }"; $response = Illuminate\Support\Facades\Http::withHeaders(["Authorization" => $apiKey, "Content-Type" => "application/json"])->post("https://api.linear.app/graphql", ["query" => $query]); dump($response->json());'
```

Find the label with `name = "Bug"` and set:

```dotenv
LINEAR_BUG_LABEL_ID=<bug-label-uuid>
```

### Apply New Env Values

```bash
php artisan config:clear
```

### Smoke Test

```bash
php artisan tinker --execute 'Log::channel("linear")->error("Linear smoke test", ["source" => "readme-setup"]);'
```

If you see `Invalid scope: read required`, create a new Linear API key with read scope (and issue create/write scope).

## Behavior Notes

- This handler is intended for `error` and above.
- If Linear credentials are missing (`LINEAR_API_KEY` or `LINEAR_TEAM_ID`), the handler safely no-ops.
- If `LINEAR_BUG_LABEL_ID` is set, the `Bug` label is automatically applied for `error`/`critical` logs.
- If the Linear API request fails, exceptions are swallowed to avoid recursive logging failures.

## Requirements

- PHP `^8.0`
- Laravel log/support components `^8.0` to `^13.0`
- `monolog/monolog` `^2.8` or `^3.0`
- `guzzlehttp/guzzle` `^7.9`

## License

MIT
