<?php

declare(strict_types=1);

return [
    'title' => 'Welcome to Hovera',
    'welcome' => [
        'heading' => 'Step 1 of 3 — your stable on Hovera',
        'body' => 'We will walk you through the 3 most important settings. Each step links to the relevant page — fill it in now or skip and come back later.',
    ],
    'steps' => [
        'company' => [
            'title' => 'Company details',
            'description' => 'VAT ID, name, address, terms',
            'body' => 'Enter your stable VAT ID — click "Fetch from GUS / VIES" and we will auto-fill the name and address. These details land on invoices issued to clients.',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'Stable settings',
        ],
        'ksef' => [
            'title' => 'KSeF',
            'description' => 'Certificate + environment (test / prod)',
            'body' => 'KSeF is mandatory in Poland from February 2026. Upload your certificate (PFX or PEM) and choose the environment. You can start in test mode and switch to prod before February.',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'KSeF settings',
        ],
        'first_record' => [
            'title' => 'First client or horse',
            'description' => 'Test the panel on a real case',
            'body' => 'The fastest way to learn the panel is to add a first record. Add a client (GUS lookup works there too) or a horse — you can edit everything later.',
            'cta_client' => 'Add first client',
            'cta_horse' => 'Add first horse',
        ],
    ],
    'action' => [
        'finish' => 'Finish wizard',
        'skip' => 'Skip wizard',
    ],
    'notify' => [
        'completed_title' => 'Wizard completed',
        'completed_body' => 'Good luck! When you need help — `?` in the panel shows shortcuts, and `Help` in the menu opens the docs.',
        'skipped_title' => 'Wizard skipped',
        'skipped_body' => 'You can always come back to configure KSeF and company data under Settings.',
    ],
];
