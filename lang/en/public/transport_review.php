<?php

declare(strict_types=1);

return [
    'anonymous_customer' => 'Customer',

    'form' => [
        'title' => 'Review for :transporter — Hovera',
        'heading' => 'Leave a review',
        'lead' => 'Your review of :transporter helps other horse owners find a trustworthy transporter. The comment field is optional.',
        'rating_label' => 'Your rating (1–5)',
        'comment_label' => 'Comment (optional)',
        'comment_placeholder' => 'What went well? What could be improved? Your review will be shown on the transporter\'s profile.',
        'comment_hint' => 'Max 2000 chars. The comment is public and signed with your first name and last-name initial (e.g. "Jan K.").',
        'submit' => 'Submit review',
        'disclaimer_intermediary' => 'Your review is published <strong>verbatim</strong> on the transporter\'s profile. Hovera = transport marketplace (<a href="/regulamin-marketplace" target="_blank">terms</a>), not a party to the carriage contract. The transporter can flag a review for moderation if they consider it factually wrong.',
    ],

    'thanks' => [
        'title' => 'Thanks for your review — Hovera',
        'heading' => 'Thank you!',
        'body' => 'Your review has been published on the transporter\'s profile. We appreciate every second you spent — it really helps other horse owners.',
        'disclaimer_intermediary' => 'Hovera publishes marketplace reviews verbatim. You can see your review on the <a href="/regulamin-marketplace" target="_blank">transporter\'s page</a> (link in the original email).',
    ],

    'already' => [
        'title' => 'Review already submitted — Hovera',
        'heading' => 'You already left a review',
        'body' => 'Thank you! Your review is already published. Each link only works once — that\'s our safeguard against duplicates.',
        'see_profile' => 'See transporter profile',
    ],

    'expired' => [
        'title' => 'Link expired — Hovera',
        'heading' => 'Review link expired',
        'body' => 'The review link was valid for 30 days. If you still want to share an opinion — drop a line to office@hovera.app and we\'ll help.',
    ],

    'section' => [
        'title' => 'Customer reviews',
        'count' => '{1} :count review|[2,*] :count reviews',
        'distribution_label' => 'Rating distribution',
        'verified_badge' => 'Verified review after a completed transport',
        'response_label' => 'Reply from :transporter',
    ],
];
