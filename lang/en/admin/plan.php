<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identification',
            'pricing' => 'Pricing',
            'stripe' => 'Stripe Price IDs',
            'stripe_description' => 'Price IDs from Stripe Dashboard (Products → Pricing). Required for Stripe Checkout subscriptions — without them customer cannot pay.',
            'limits' => 'Limits',
            'limits_description' => 'Hard plan limits — enforced in app (CreateTenant blocks when plan is exceeded).',
            'features' => 'Features',
            'features_description' => 'Marketing bullet points + feature flags for the feature-flag system.',
            'visibility' => 'Visibility',
        ],
        'helper' => [
            'code' => 'Unique identifier (e.g. free, stable, pro). Used in API + links.',
            'sort_order' => 'Lower = higher in the list.',
            'price_yearly' => 'Usually 10× monthly minus a 10-30% annual discount.',
            'stripe_price_monthly_id' => 'Copy from Stripe Dashboard → Products → specific plan → Pricing → click price ID.',
            'stripe_price_yearly_id' => 'Second Price ID for the yearly variant (usually Recurring → Yearly).',
            'onboarding_fee' => 'One-time onboarding fee charged at first checkout. Required for any paid plan (leave Free at empty or 0).',
            'limits' => 'Standard keys: max_horses, max_clients, max_users, max_storage_mb. -1 = unlimited.',
            'features' => 'Keys: bullets[N]=string (marketing), enabled.X=bool (feature flag).',
            'is_active' => 'Whether the plan can still be assigned to new tenants.',
            'is_public' => 'Whether to show on the public pricing page. Enterprise usually false (custom).',
            'audience' => 'Who this plan is for — Stable or Transporter. Immutable after creation.',
        ],
        'label' => [
            'audience' => 'Audience',
            'price_monthly' => 'Monthly price',
            'price_yearly' => 'Yearly price',
            'stripe_price_monthly_id' => 'Stripe Price ID (monthly)',
            'stripe_price_yearly_id' => 'Stripe Price ID (yearly)',
            'onboarding_fee' => 'Onboarding fee (one-time)',
            'is_active' => 'Active',
            'is_public' => 'Public in pricing',
            'kv_key' => 'Key',
            'kv_value' => 'Value',
        ],
    ],

    'table' => [
        'column' => [
            'audience' => 'Audience',
            'price_monthly' => 'Monthly',
            'price_yearly' => 'Yearly',
            'tenants_count' => 'Stables',
            'is_active_short' => 'Act.',
            'is_public_short' => 'Pub.',
        ],
        'filter' => [
            'audience' => 'Audience',
        ],
    ],

    'action' => [
        'delete_blocked_title' => "Can't delete — plan is in use.",
        'delete_blocked_body' => ':count stables are on this plan. Reassign them first.',
    ],
];
