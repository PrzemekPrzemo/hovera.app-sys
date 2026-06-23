<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Hovera account activation',
    ],
    'heading' => 'Set your password',
    'intro' => 'Welcome. Set your password to activate the specialist account (:email) on Hovera.',
    'field' => [
        'password' => 'New password (min. 10 chars, letters + numbers)',
        'password_confirmation' => 'Confirm password',
    ],
    'button' => [
        'submit' => 'Activate account',
    ],
    'error' => [
        'invalid_or_expired' => 'The activation link is invalid or has expired. Ask the stable to re-send the invitation.',
    ],
    'success' => [
        'account_ready' => 'Account activated. You can log in to the specialist panel.',
    ],
    'invalid' => [
        'title' => 'Link expired',
        'heading' => 'Activation link is invalid or expired',
        'body' => 'Hovera magic links expire after 7 days or after single use. Ask the stable that invited you to send a new invitation.',
    ],
    'completed' => [
        'title' => 'Account activated',
        'heading' => 'Account activated ✓',
        'body' => 'Your password has been set — you can now log in to the specialist panel. Your account is still pending verification by the Hovera team (usually within 24 business hours); until then stables see an "unverified" badge next to your name.',
        'login_cta' => 'Go to login',
    ],
];
