<?php

declare(strict_types=1);

return [
    'mail' => [
        'subject' => 'Hovera — :count unread messages (:tenant)',
        'greeting' => 'Hi!',
        'intro' => 'You have new messages in the internal channels of :tenant:',
        'channel_count' => ':count new',
        'cta' => 'Open Hovera',
        'footer' => 'You can disable notifications for specific channels in the channel settings.',
    ],
];
