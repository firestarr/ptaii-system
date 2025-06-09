<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Groq API Key and Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Groq API Key. This will be
    | used to authenticate with the Groq API - you can find your API key
    | on your Groq dashboard.
    */

    'api_key' => env('GROQ_API_KEY'),
    'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('GROQ_REQUEST_TIMEOUT', 30),
    
    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | Default model to use for chat completions
    */
    
    'default_model' => env('GROQ_DEFAULT_MODEL', 'llama3-8b-8192'),
];