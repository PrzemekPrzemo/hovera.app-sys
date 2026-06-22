<?php

declare(strict_types=1);

return [
    'mail' => [
        'subject' => 'Invitation to Hovera from ":tenant"',
        'greeting' => 'Hello :name,',
        'intro' => 'The stable ":tenant" (invited by :user) wants to connect with you through the Hovera panel — a horse management system where vets and specialists can access horse patients and communicate with their owners.',
        'what_is_it' => 'To accept the invitation and get access to your panel, set your password and confirm your email address by clicking the button below.',
        'cta' => 'Set password and activate account',
        'expiry' => 'The activation link expires :when. If you lose access, you can ask the stable to re-send the invitation.',
        'security_note' => 'If you did not expect this email, you can safely ignore it — without clicking the link the account will not be activated.',
        'salutation' => 'Regards, the Hovera team',
    ],
];
