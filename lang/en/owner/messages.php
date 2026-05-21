<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'You are not the owner of this horse.',
        'send_requires_active_boarding' => 'Sending messages requires an active boarding assignment. Boarding ended — historical messages remain readable.',
    ],

    'attachment' => [
        'too_large' => 'Attachment ":name" exceeds the :max_mb MB limit.',
        'unsupported_mime' => 'Unsupported file type ":mime" (":name"). Allowed: images, PDF, MP4/MOV video.',
    ],
];
