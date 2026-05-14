<?php

return [
    'rag_api' => [
        'url' => env('RAG_API_URL', 'http://rag-api:8000'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:1b'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'azure' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID', 'common'), // multi-tenant
    ],
];
