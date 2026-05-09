<?php

declare(strict_types=1);

return [
    'title' => 'Create your stable',
    'thanks_title' => 'Check your inbox',

    'heading' => 'Create your stable on hovera',
    'subtitle' => 'Fill in 4 fields, you\'ll get an email with a link to set your password. No credit card.',

    'trial_strong' => '🎉 30 days free',
    'trial_text' => 'Full functionality. No card. No auto-charge — pick a plan only when you\'re sure you want to stay.',

    'label' => [
        'name' => 'Stable name',
        'slug' => 'Stable URL',
        'owner_name' => 'Your full name',
        'owner_email' => 'Email',
        'terms' => 'I accept the <a href="/regulamin" target="_blank">terms</a> and <a href="/polityka-prywatnosci" target="_blank">privacy policy</a>',
    ],

    'placeholder' => [
        'name' => 'Pegasus Stables',
        'owner_name' => 'Anna Kowalska',
    ],

    'helper' => [
        'name' => 'This is what shows in the panel and on the public page.',
        'slug' => 'Lowercase letters, digits and dashes only. Min. 3 chars.',
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
        'provisioning_failed' => 'Something went wrong on our side. Try again in a moment or email support@hovera.app.',
    ],

    'thanks_heading' => '✓ Account created',
    'thanks_subtitle' => 'Stable :tenant has been created. We sent an email with a link to set your password.',
    'thanks_step_1' => 'Check your inbox (link valid for 7 days).',
    'thanks_step_2' => 'Click "Accept invitation" in the email.',
    'thanks_step_3' => 'Set a password — this is your first login to the panel.',
    'thanks_step_4' => 'Land in /app with 30 days of trial, no limits.',
    'thanks_no_email' => 'No email after 5 minutes?',
    'thanks_no_email_help' => 'Check spam. If still nothing — email support@hovera.app, we\'ll help.',
];
