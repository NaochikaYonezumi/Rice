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

    // Microsoft 365 メール (IMAP/SMTP XOAUTH2) 用. ログイン用の azure とは別 App でも OK.
    // 既存ログイン用と同じ App を使うなら同じ client_id/secret を流用可能.
    'microsoft_mail' => [
        'client_id'     => env('MICROSOFT_MAIL_CLIENT_ID',     env('MICROSOFT_CLIENT_ID')),
        'client_secret' => env('MICROSOFT_MAIL_CLIENT_SECRET', env('MICROSOFT_CLIENT_SECRET')),
        'tenant'        => env('MICROSOFT_MAIL_TENANT_ID',     env('MICROSOFT_TENANT_ID', 'common')),
        // OAuth redirect_uri. .env で未指定なら APP_URL から組み立て.
        'redirect_uri'  => env('MICROSOFT_MAIL_REDIRECT_URI'),
    ],
];
