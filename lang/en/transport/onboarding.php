<?php

declare(strict_types=1);

return [
    'title' => 'Welcome to Hovera Transport',
    'navigation_label' => 'First steps',
    'welcome' => [
        'heading' => 'Step 1 of 3 — your transport company on Hovera',
        'body' => 'We will walk you through the 3 most important settings: PLW documents, service areas and KSeF. The whole thing takes 10-15 minutes.',
    ],
    'steps' => [
        'documents' => [
            'title' => 'PLW documents',
            'description' => 'Verification by Hovera team',
            'body' => 'Upload the 6 required documents: GITD permit, PLW permit (Type 1/2), driver licences, vehicle approval certificates, washing log, liability insurance. Without verification you cannot send offers. Details in "Help centre → Transport company → PLW documents".',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'Upload documents',
        ],
        'coverage' => [
            'title' => 'Service areas + pricing',
            'description' => 'Where you operate and at what rate',
            'body' => 'Mark the voivodeships you operate in (catalog filter shows you to regional clients) and set base pricing (PLN/km + minimum). The pricing calculator uses these as defaults — you can override per quote.',
            'cta_areas' => 'Service areas',
            'cta_pricing' => 'Base pricing',
        ],
        'ksef' => [
            'title' => 'KSeF',
            'description' => 'Certificate + environment',
            'body' => 'KSeF is mandatory from Feb 2026. Upload the certificate (PFX/PEM) and pick test (for learning) or prod (after February). Without KSeF you can still issue invoices locally, but sending to MF will be manual.',
            'cta_hint' => 'Open in a new tab:',
            'cta' => 'KSeF settings (tab in Transport Settings)',
        ],
    ],
    'action' => [
        'finish' => 'Finish wizard',
        'skip' => 'Skip wizard',
    ],
    'notify' => [
        'completed_title' => 'Wizard completed',
        'completed_body' => 'Once the Hovera team verifies your documents, your company will appear in the public /przewoznicy directory.',
        'skipped_title' => 'Wizard skipped',
        'skipped_body' => 'PLW documents must be uploaded before your first offer — come back to Transport Settings whenever you are ready.',
    ],
];
