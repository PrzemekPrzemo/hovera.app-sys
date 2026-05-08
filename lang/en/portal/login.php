<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Client portal — :tenant',
        'heading' => 'Client portal — :tenant',
        'intro' => 'Enter the email address used for booking confirmations. We will send you a sign-in link.',
        'email' => 'Email',
        'submit' => 'Send sign-in link',
        'back' => '← Back to stable page',
    ],

    'sent' => [
        'title' => 'Check your inbox — :tenant',
        'heading' => 'Check your inbox',
        'body' => 'If <strong>:email</strong> is linked to an account at <strong>:tenant</strong>, we sent you a sign-in link.',
        'ttl' => 'The link is valid for 30 minutes.',
        'back' => '← Back',
    ],

    'invalid' => [
        'title' => 'Link inactive — :tenant',
        'heading' => 'Link inactive',
        'body' => 'This sign-in link has expired or has already been used. Links are single-use and valid for 30 minutes.',
        'request_new' => 'Send a new link',
    ],
];
