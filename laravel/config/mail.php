<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => env('MAIL_HOST', '127.0.0.1'),
            'port'       => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username'   => env('MAIL_USERNAME'),
            'password'   => env('MAIL_PASSWORD'),
            'timeout'    => null,
        ],
        'log' => ['transport' => 'log'],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Mail RAG'),
    ],

    // -------------------------------------------------------
    // 受信設定（IMAP / POP3）
    // -------------------------------------------------------
    'inbox' => [
        'protocol' => env('MAIL_INBOX_PROTOCOL', 'imap'), // 'imap' or 'pop3'

        'imap' => [
            'host'       => env('MAIL_IMAP_HOST'),
            'port'       => (int) env('MAIL_IMAP_PORT', 993),
            'encryption' => env('MAIL_IMAP_ENCRYPTION', 'ssl'), // ssl / tls / null
            'username'   => env('MAIL_IMAP_USERNAME'),
            'password'   => env('MAIL_IMAP_PASSWORD'),
            'folder'     => env('MAIL_IMAP_FOLDER', 'INBOX'),
        ],

        'pop3' => [
            'host'       => env('MAIL_POP_HOST'),
            'port'       => (int) env('MAIL_POP_PORT', 995),
            'encryption' => env('MAIL_POP_ENCRYPTION', 'ssl'), // ssl / null
            'username'   => env('MAIL_POP_USERNAME'),
            'password'   => env('MAIL_POP_PASSWORD'),
        ],
    ],
];
