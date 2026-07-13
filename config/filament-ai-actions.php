<?php

return [

    'api_key' => env('OPENAI_API_KEY'),

    'organization' => env('OPENAI_ORGANIZATION'),

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    'timeout' => (int) env('OPENAI_TIMEOUT', 60),

    'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Default generation options
    |--------------------------------------------------------------------------
    */

    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.4),

    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 1200),

];
