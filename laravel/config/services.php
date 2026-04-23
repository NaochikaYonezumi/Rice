<?php

return [
    'rag_api' => [
        'url' => env('RAG_API_URL', 'http://rag-api:8000'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
    ],
];
