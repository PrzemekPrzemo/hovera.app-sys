<?php

declare(strict_types=1);

return [
    'title' => 'Join as a carrier',
    'heading' => 'Join hovera as a transport company',
    'subtitle' => 'Horse transport marketplace — fill in your profile in 10 minutes, upload documents, '
        .'and we will verify your account in 2-3 business days. Customers will find you in the /przewoznicy directory.',

    'no_commission_banner' => 'hovera.app charges no commission from carriers or customers — '
        .'just a monthly subscription.',

    'promo' => [
        'heading' => '🎁 Promotion until end of July 2026',
        'body' => 'Full functionality. No card. No auto-conversion to paid — '
            .'choose a plan only when you are sure you want to stay.',
        'body_yearly' => 'Registration before end of July 2026 grants 30 days free, '
            .'and the annual price equals 10 × the monthly rate (2 months free). '
            .'Limited-time offer.',
    ],

    'perks' => [
        'title' => 'What you get',
        'item_1' => 'Public profile at app.hovera.app/t/{slug} indexed by Google',
        'item_2' => 'Direct leads from customers + broadcast to all of Poland (PLW)',
        'item_3' => 'Quote calculator + automatic PDF offer + online payment',
        'item_4' => 'First month free starting from the document verification date',
    ],

    'section' => [
        'company' => 'Company details',
        'owner' => 'Contact — owner / authorized person',
        'documents' => 'Required documents',
        'terms' => 'Terms and consents',
    ],

    'field' => [
        'name' => 'Full company name',
        'name_hint' => 'As listed on Tax ID / business registry.',
        'slug' => 'Marketplace URL (slug)',
        'slug_hint' => 'Lowercase letters, digits and hyphens only. Immutable after registration. E.g. "galoptrans" → app.hovera.app/t/galoptrans',
        'tax_id' => 'Tax ID (NIP)',
        'tax_id_hint' => 'Numbers only — 10 digits, no dashes.',
        'regon' => 'REGON',
        'regon_hint' => '9 or 14 digits.',
        'address' => 'Company address',
        'owner_name' => 'Full name',
        'owner_email' => 'Contact email',
        'owner_email_hint' => 'We will send a magic link to the panel after verification.',
        'owner_phone' => 'Contact phone',
    ],

    'documents_disclaimer' => 'We require 6 documents issued by the Polish County Veterinary Officer '
        .'(PLW — Powiatowy Lekarz Weterynarii) per EC Regulation 1/2005 and hovera regulations. '
        .'Without the full set, we cannot activate your account. Formats: PDF, JPG, PNG. Max 5 MB per file.',

    'pwl_authorization' => [
        'label' => 'PLW carrier authorization',
        'description' => 'Authorization to transport live animals under EC Regulation 1/2005. '
            .'Choose the type: T1 (short trips, up to 8h) or T2 (long trips, over 8h) — '
            .'and upload the matching scan.',
        'type_t1' => 'Type 1 (transport up to 8 hours)',
        'type_t2' => 'Type 2 (transport over 8 hours)',
    ],

    'documents' => [
        'file_hint' => 'PDF, JPG or PNG. Max 5 MB.',
        'anonymized_heading' => 'Documents are only for verification',
        'anonymized_body' => 'After positive verification by the Hovera team, we show documents to customers ONLY '
            .'in anonymized form (no serial numbers, expiry dates, personal data). '
            .'Publicly visible is ONLY: "✓ Verified by the Hovera team".',
    ],

    'terms' => [
        'marketplace_position' => 'Hovera is a marketplace platform for transport companies. '
            .'We are not a carrier and not a party to the transport contract — we technically '
            .'connect customers with transport companies. The transport contract is made directly '
            .'between you and the customer, payment goes to your account (P24/PayU/Stripe).',
        'accept_html' => 'I accept :regulamin, :marketplace and :privacy. I declare that '
            .'the uploaded documents are valid and compliant with applicable law.',
        'regulamin' => 'hovera Terms',
        'marketplace' => 'Marketplace Terms',
        'privacy' => 'Privacy Policy',
    ],

    'submit' => 'Submit registration',

    'errors' => [
        'heading' => 'Check the form:',
        'slug_format' => 'Slug may contain only lowercase letters, digits and hyphens (e.g. "galoptrans").',
        'slug_taken' => 'This slug is already taken — choose a different one.',
        'tax_id_format' => 'Tax ID must be 10 digits (no dashes or spaces).',
        'regon_format' => 'REGON must be 9 or 14 digits.',
        'terms' => 'You must accept the terms to continue.',
        'pwl_authorization_type_required' => 'Choose the PLW authorization type (T1 or T2).',
        'provisioning_failed' => 'Unfortunately we could not create the account — please try again in a moment. '
            .'If the problem persists, please email office@hovera.app.',
    ],

    'notify' => [
        'thanks_silent' => 'Thank you — we will review your submission and get back to you.',
    ],

    'thanks' => [
        'title' => 'Thank you for registering',
        'heading' => 'Submission received!',
        'intro' => 'The account for ":name" has been created and is awaiting document verification.',
        'step_1' => 'We will verify your documents within 2-3 business days.',
        'step_2' => 'On successful verification we will send a magic link to your email.',
        'step_3' => 'A 1-month trial then starts — you can add vehicles, drivers and accept orders.',
        'contact_hint' => 'Have a question? Email :email.',
        'cta_directory' => 'Browse the carriers directory',
    ],
];
