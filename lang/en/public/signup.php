<?php

declare(strict_types=1);

return [
    'title' => 'Create your account',
    'title_stable' => 'Create your stable',
    'title_transporter' => 'Create your transport company',
    'thanks_title' => 'Check your inbox',

    'heading' => 'Create your account on hovera',
    'heading_stable' => 'Create your stable on hovera',
    'heading_transporter' => 'Create your transport company on hovera',
    'subtitle' => 'Fill in 4 fields, you\'ll get an email with a link to set your password. No credit card.',
    'subtitle_stable' => 'Fill in 4 fields, you\'ll get an email with a link to set your password. No credit card.',
    'subtitle_transporter' => 'Fill in 4 fields, you\'ll get an email with a link to set your password. 30-day trial — Pro plan (5 vehicles, 10 drivers).',
    'back_to_choose' => 'back to account type',

    'choose' => [
        'title' => 'What do you run?',
        'heading' => 'What do you run?',
        'subtitle' => 'Hovera runs two distinct businesses in one ecosystem. Pick the right one — you can add the other later.',
        'stable' => [
            'title' => 'I run a stable',
            'price' => 'from €0 / mo · 30-day trial',
            'bullet_1' => 'Multi-resource calendar: lessons, training, care',
            'bullet_2' => 'Clients + passes + auto-settlement',
            'bullet_3' => 'Horse card, health journal, feeding plan',
            'bullet_4' => 'VAT invoices + KSeF + owner portal',
            'cta' => 'Register stable →',
        ],
        'transporter' => [
            'title' => 'I run a transport company',
            'price' => 'from €35 / mo · 30-day trial',
            'bullet_1' => 'Vehicles + drivers + per-km/fuel pricing',
            'bullet_2' => 'Route calculator with map (ORS/Mapbox/Google)',
            'bullet_3' => 'PDF quotes + numbering + email delivery',
            'bullet_4' => 'Marketplace of inquiries from stables',
            'cta' => 'Register company →',
        ],
    ],

    'trial_strong' => '🎉 30 days free',
    'trial_text' => 'Full functionality. No card. No auto-charge — pick a plan only when you\'re sure you want to stay.',

    'label' => [
        'name' => 'Stable name',
        'name_stable' => 'Stable name',
        'name_transporter' => 'Company name',
        'slug' => 'Stable URL',
        'slug_transporter' => 'Transport company URL',
        'owner_name' => 'Your full name',
        'owner_email' => 'Email',
        'terms' => 'I accept the <a href="/regulamin" target="_blank">terms</a> and <a href="/polityka-prywatnosci" target="_blank">privacy policy</a>',
        'terms_marketplace_suffix' => ' and the <a href="/regulamin-marketplace" target="_blank">transport marketplace terms</a> (Hovera provides technology intermediation services — it is not a carrier nor a party to the transport contract)',
    ],

    'placeholder' => [
        'name' => 'Pegasus Stables',
        'owner_name' => 'Anna Kowalska',
    ],

    'helper' => [
        'name' => 'This is what shows in the panel and on the public page.',
        'slug' => 'Lowercase letters, digits and dashes only. Min. 3 chars.',
        'slug_transporter' => 'Lowercase letters, digits and dashes only. Min. 3 chars. Your marketplace profile will live at this address.',
        'owner_email' => 'We send a password setup link here.',
    ],

    'action' => [
        'submit' => 'Create account + 30 days free',
    ],

    'footer' => [
        'demo' => 'See the demo first',
        'pricing' => 'See pricing',
        'login' => 'I already have an account',
    ],

    'errors' => [
        'heading' => 'Please review:',
        'slug_format' => 'URL can only contain lowercase letters, digits and dashes (no leading/trailing dashes).',
        'slug_taken' => 'That URL is taken — try another, e.g. add a city or initials.',
        'terms' => 'You must accept the terms.',
        'provisioning_failed' => 'Something went wrong on our side. Try again in a moment or email office@hovera.app.',
    ],

    'thanks_heading' => '✓ Account created',
    'thanks_subtitle' => 'Stable :tenant has been created. We sent an email with a link to set your password.',
    'thanks_step_1' => 'Check your inbox (link valid for 7 days).',
    'thanks_step_2' => 'Click "Accept invitation" in the email.',
    'thanks_step_3' => 'Set a password — this is your first login to the panel.',
    'thanks_step_4' => 'Land in /app with 30 days of trial, no limits.',
    'thanks_no_email' => 'No email after 5 minutes?',
    'thanks_no_email_help' => 'Check spam. If still nothing — email office@hovera.app, we\'ll help.',
];
