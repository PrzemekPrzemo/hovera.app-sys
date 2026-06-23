<?php

declare(strict_types=1);

return [
    'nav' => 'Specialists — messages',
    'model' => 'Specialist thread',
    'model_plural' => 'Specialist threads',
    'messages' => 'Messages',

    'form' => [
        'specialist' => 'Specialist',
        'specialist_hint' => 'Lists specialists you have invited. To add a new one, use "Invite a specialist".',
        'horse' => 'Horse (optional)',
        'horse_placeholder' => '— general thread —',
        'horse_hint' => 'The selected horse is shared with the specialist in this thread.',
        'subject' => 'Subject',
        'body' => 'Message body',
    ],

    'table' => [
        'subject' => 'Subject',
        'specialist' => 'Specialist',
        'last_message' => 'Last message',
    ],

    'action' => [
        'new' => 'New thread',
        'open' => 'Open',
        'reply' => 'Reply',
    ],

    'sender' => [
        'specialist' => 'Specialist',
        'you' => 'You',
    ],

    'invite' => [
        'label' => 'Invite a specialist',
        'email' => 'Specialist email',
        'display_name' => 'Full name',
        'specialty' => 'Specialty',
        'modal_heading' => 'Invite specialist to Hovera',
        'modal_description' => 'We will send a 7-day activation link. After setting a password the specialist can reply to your messages.',
        'submit' => 'Send invitation',
        'no_context' => 'No account context — refresh the page and try again.',
        'sent_title' => 'Invitation sent',
        'sent_body' => 'The specialist (:email) will receive an activation link by email.',
        'exists_title' => 'Specialist already registered',
        'exists_body' => ':email is already registered — you can start a thread right away.',
    ],

    'error' => [
        'no_context' => 'No account context — refresh the page and try again.',
    ],
];
