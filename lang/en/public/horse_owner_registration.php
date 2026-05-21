<?php

declare(strict_types=1);

return [
    'meta' => [
        'title' => 'Create a free horse owner account — Hovera',
        'description' => 'Order transport, track your horse documents, get quotes from verified carriers. Free.',
    ],

    'heading' => 'Create your horse owner account',
    'subheading' => 'Free account — no subscription, no credit card. Order transport, keep your data in one place.',

    'form' => [
        'label' => [
            'owner_name' => 'Full name',
            'owner_email' => 'Email',
            'owner_phone' => 'Phone (optional)',
            'terms' => 'I accept the :terms and :privacy',
            'terms_link' => 'terms',
            'privacy_link' => 'privacy policy',
        ],
        'placeholder' => [
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@example.com',
            'owner_phone' => '+48 123 456 789',
        ],
        'submit' => 'Create account (free)',
    ],

    'features' => [
        'heading' => 'What you get in the free account',
        'order_transport' => [
            'title' => 'Order transport',
            'body' => 'Post a request, get quotes from verified carriers in your area.',
        ],
        'horse_docs' => [
            'title' => 'Your horse documents',
            'body' => 'Passport, vaccinations, vet records — all in one place, always at hand.',
        ],
        'stable_relation' => [
            'title' => 'Your stable on Hovera.app',
            'body' => 'If your stable uses Hovera — pay for boarding, oversee vet checks, farrier visits and feeding plans for your horse.',
        ],
        'history' => [
            'title' => 'Transport & quote history',
            'body' => 'All previous orders, quotes and invoices visible in your panel.',
        ],
    ],

    'stable_hint' => [
        'banner' => 'If your stable already uses Hovera.app, after creating an account you can link your horse to its boarding — pay for the stay, oversee vet checks, farrier visits and feeding plans directly from your panel.',
    ],

    'invite' => [
        'banner' => 'You were invited by a stable — after registration your account will be automatically linked.',
    ],

    'errors' => [
        'provisioning_failed' => 'Could not create the account — try again in a moment or contact us.',
        'terms' => 'You must accept the terms and privacy policy.',
    ],

    'thanks' => [
        'heading' => 'Account created — check your email',
        'body' => 'We sent you an email with a link to set your password. After setting it you can log in and see your panel.',
        'next_steps' => 'After logging in:',
        'step_horses' => 'Add your horses',
        'step_transport' => 'Post your first transport request',
        'step_documents' => 'Upload documents (passports, vaccinations)',
        'open_login' => 'Open login',
        'invite_origin_heading' => 'Stable :stable is waiting',
        'invite_origin_body' => 'You were invited by stable ":stable" to start boarding. After logging in add your horse — the stable will then be able to send you a boarding request (or you can send the first one from your panel).',
    ],
];
