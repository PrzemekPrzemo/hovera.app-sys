<?php

declare(strict_types=1);

return [
    'navigation' => 'Embed form',
    'title' => 'Inquiry form to embed',

    'section' => [
        'origins' => 'Allowed domains',
        'origins_description' => 'Only the listed domains can submit the form. Enter the full URL with scheme (`https://` or `http://`), no trailing slash.',
        'token' => 'API token',
        'token_description' => 'Secret verified via `X-Hovera-Embed-Token` header. Regenerating invalidates the old token immediately — update the snippet on your sites.',
        'snippet' => 'Snippet to paste',
        'snippet_description' => 'Copy and paste into your site\'s HTML. JS posts the inquiry to Hovera; transport payments go directly to you (Hovera does not handle the money).',
    ],

    'form' => [
        'origin_url' => 'Site URL (Origin)',
        'add_origin' => 'Add domain',
        'token_status_label' => 'Token status',
        'token_missing' => 'No token — generate one to activate the embed.',
        'token_present' => 'Token set (:preview).',
    ],

    'action' => [
        'save' => 'Save domains',
        'regenerate_token' => 'Generate new token',
        'regenerate_token_confirm' => 'The old token will stop working immediately — all existing embeds will need to be updated. Continue?',
        'copy' => 'Copy snippet',
        'copied' => 'Copied!',
    ],

    'notify' => [
        'saved' => 'Domains saved',
        'saved_body' => 'Active domains: :count.',
        'token_regenerated' => 'New token generated',
        'token_regenerated_body' => 'The old token is no longer valid. Update the snippet on your sites.',
    ],

    'snippet' => [
        'requires_token' => '<!-- Generate an API token above first to see the snippet code. -->',
    ],
];
