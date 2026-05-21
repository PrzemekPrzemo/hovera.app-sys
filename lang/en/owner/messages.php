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

    'page' => [
        'title' => 'Messages with stable',
        'breadcrumb' => 'Messages',
        'thread_with' => 'Thread with',
        'empty_heading' => 'No messages',
        'empty_description' => 'Send the first message to the stable — it will appear here along with replies.',
    ],

    'form' => [
        'section' => 'New message',
        'subject' => 'Subject (optional)',
        'body' => 'Message',
        'attachments' => 'Attachments',
        'attachments_hint' => 'Up to 10 files, 25 MB each. Images (JPG/PNG/WebP), PDF, MP4/MOV video.',
        'send' => 'Send',
        'sent_title' => 'Message sent.',
        'empty_body' => 'Message body cannot be empty.',
        'attachments_failed' => 'Failed to upload attachments.',
    ],
];
