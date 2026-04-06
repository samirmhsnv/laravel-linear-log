<?php

return [
    'name' => env('LINEAR_LOG_NAME', 'linear'),
    'api_key' => env('LINEAR_API_KEY'),
    'team_id' => env('LINEAR_TEAM_ID'),
    'project_id' => env('LINEAR_PROJECT_ID'),
    'state_id' => env('LINEAR_STATE_ID'),
    'bug_label_id' => env('LINEAR_BUG_LABEL_ID'),
    'priority' => env('LINEAR_PRIORITY'),
    'title_prefix' => env('LINEAR_TITLE_PREFIX', env('APP_NAME', 'Laravel')),
    'timeout' => env('LINEAR_TIMEOUT', 3.0),
    'connect_timeout' => env('LINEAR_CONNECT_TIMEOUT', 1.5),
];
