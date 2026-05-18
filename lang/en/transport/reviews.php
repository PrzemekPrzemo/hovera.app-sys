<?php

declare(strict_types=1);

return [
    'navigation' => 'Customer reviews',
    'model' => [
        'singular' => 'Review',
        'plural' => 'Reviews',
    ],
    'table' => [
        'column' => [
            'rating' => 'Rating',
            'customer' => 'Customer',
            'comment' => 'Comment',
            'status' => 'Status',
            'responded' => 'Replied',
            'submitted_at' => 'Submitted',
        ],
    ],
    'filter' => [
        'rating' => 'Rating',
    ],
    'status' => [
        'invited' => 'invited',
        'published' => 'published',
        'hidden' => 'hidden',
        'flagged' => 'flagged',
        'expired' => 'expired',
    ],
    'action' => [
        'respond' => 'Reply publicly',
        'flag' => 'Flag for moderation',
    ],
    'form' => [
        'response_label' => 'Your reply',
        'response_helper' => 'Your reply is shown publicly under the review. You can edit it later.',
        'flag_reason_label' => 'Reason for flagging',
        'flag_reason_helper' => 'Explain why this review breaches the rules (defamation, fake, factually wrong). Hovera staff will review it.',
    ],
    'notify' => [
        'response_saved' => 'Reply saved.',
        'flagged_title' => 'Review flagged for moderation',
        'flagged_body' => 'The review is temporarily hidden. Hovera staff will decide.',
    ],
    'stats' => [
        'average' => 'Average rating',
        'count' => 'Total reviews',
        'count_desc' => 'published',
        'five_stars' => '5-star ratings',
        'no_reviews_yet' => 'No reviews yet',
    ],
    'view' => [
        'section_review' => 'Review',
        'section_response' => 'Your reply',
        'section_moderation' => 'Moderation',
    ],
];
