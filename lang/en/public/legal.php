<?php

declare(strict_types=1);

return [
    'last_updated' => '2026-05-09',
    'last_updated_label' => 'Last updated',

    'nav' => [
        'pricing' => 'Pricing',
        'demo' => 'Demo',
        'signup' => 'Create stable',
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'dpa' => 'DPA',
    ],

    'footer' => [
        'copyright' => '© :year hovera. All rights reserved.',
        'support' => 'Support: support@hovera.app',
    ],

    'terms' => [
        'title' => 'hovera Terms of Service',
        'intro' => 'These Terms govern access to and use of the hovera service available at app.hovera.app, provided by Sendormeco Holding sp. z o.o. (the "Provider") to businesses operating riding stables and related activities (the "Customer" or "Stable").',

        'section_1_heading' => '1. Definitions',
        'section_1_body' => 'Service — the hovera SaaS platform for stable management (bookings, clients, horses, billing, communications). Customer — the business that has entered into a service agreement. User — a natural person using the service on behalf of the Customer (owner, instructor, employee, rider). Trial — the 30-day free period from registration. Plan — a feature package described on the pricing page.',

        'section_2_heading' => '2. Scope of service',
        'section_2_body' => 'The Service includes: lesson calendar, client and horse records, billing (invoices, online payments), public stable micro-site, online booking form, customer communications (email, SMS), financial reports and related features. The full feature list per Plan is available at /pricing.',

        'section_3_heading' => '3. Registration and contract formation',
        'section_3_body' => 'Registration occurs via /signup. The Customer provides stable name, stable URL, owner contact details, and accepts these Terms and the privacy policy. The agreement is formed when the Provider creates the tenant and sends an activation link to the provided email. The Customer warrants the accuracy of submitted data.',

        'section_4_heading' => '4. Trial period',
        'section_4_body' => 'Each new Customer receives a 30-day free trial with full Pro plan functionality. After 30 days, panel access is restricted to "read-only" until a plan is selected and paid for. The trial does NOT auto-convert to a paid subscription — the Customer chooses when to start payment.',

        'section_5_heading' => '5. Fees and payments',
        'section_5_body' => 'Fees and plan scope are set out in the current pricing page at /pricing. Fees are charged in advance per billing period (monthly or annual) via the payment provider (Stripe). VAT invoices are issued electronically and emailed to the Customer. Late payment of more than 14 days entitles the Provider to suspend panel access (with data retention of 90 days from suspension).',

        'section_6_heading' => '6. Complaints',
        'section_6_body' => 'Complaints should be submitted to support@hovera.app within 14 days of the event. The complaint should include Customer details, problem description, and the desired resolution. The Provider will respond within 14 working days. No response within the deadline is treated as acceptance of the complaint.',

        'section_7_heading' => '7. Availability (SLA)',
        'section_7_body' => 'The Provider commits to 99.5% monthly availability, excluding planned maintenance windows announced 48 hours in advance. If the unavailability threshold is exceeded, the Customer is entitled to a credit proportional to downtime (per the SLA schedule available on request).',

        'section_8_heading' => '8. Liability',
        'section_8_body' => 'The Provider\'s liability for non-performance or improper performance is limited to fees paid by the Customer in the preceding 12 months. The Provider is not liable for lost profits, indirect damages, or harm caused by misuse of the service by the Customer\'s Users. This limitation does not apply to wilful misconduct or breaches of GDPR.',

        'section_9_heading' => '9. Governing law and jurisdiction',
        'section_9_body' => 'Matters not covered by these Terms are governed by Polish law, in particular the Civil Code and the Act on Providing Services by Electronic Means. Disputes are subject to the court of competent jurisdiction over the Provider\'s registered office (Warsaw).',

        'section_10_heading' => '10. Changes to the Terms',
        'section_10_body' => 'The Provider reserves the right to amend these Terms. Changes will be communicated by email at least 30 days in advance. If the Customer does not accept changes, they may terminate the agreement with immediate effect before the changes take effect.',

        'section_11_heading' => '11. Contact',
        'section_11_body' => 'Questions about these Terms: support@hovera.app. Provider details: Sendormeco Holding sp. z o.o., registered office at ul. Złota 75A/7, 00-819 Warsaw, Poland; NIP (Polish tax ID): 5252866457; REGON: 389194801; registered in the National Court Register (KRS) under number 0000906110, kept by the District Court for the Capital City of Warsaw, 12th Commercial Division of KRS; share capital PLN 5,000.',
    ],

    'privacy' => [
        'title' => 'hovera Privacy Policy',
        'intro' => 'This privacy policy describes how personal data of hovera users is processed, in accordance with GDPR (EU 2016/679) and Polish Personal Data Protection Act of 10 May 2018.',

        'section_1_heading' => '1. Data controller',
        'section_1_body' => 'The data controller is Sendormeco Holding sp. z o.o., based in Warsaw, registered office at ul. Złota 75A/7, 00-819 Warsaw, Poland; NIP: 5252866457; REGON: 389194801; KRS: 0000906110. Controller contact: privacy@hovera.app. Data Protection Officer (DPO): dpo@hovera.app.',

        'section_2_heading' => '2. Scope of processed data',
        'section_2_body' => 'During registration: full name, email, stable name, stable URL. During use of the service: IP address, session and cookie identifiers, device technical data (user-agent), activity logs (login time, panel actions). Data entered by the Customer about its clients (riders, horses) is processed as a processor — under the DPA terms (/dpa).',

        'section_3_heading' => '3. Purpose and legal basis',
        'section_3_body' => 'We process data to: (a) conclude and perform the service agreement — Art. 6(1)(b) GDPR; (b) issue invoices and tax records — Art. 6(1)(c) GDPR (legal obligation); (c) ensure system security and detect abuse — Art. 6(1)(f) GDPR (legitimate interest); (d) market our own services to existing customers — Art. 6(1)(f) GDPR (legitimate interest), with a right to object.',

        'section_4_heading' => '4. Retention period',
        'section_4_body' => 'Customer account data: for the duration of the agreement plus 90 days (reactivation grace period). Invoice data: 5 years from the end of the fiscal year (tax law obligation). System logs: 12 months. Analytics cookies: 13 months. After the retention period, data is irreversibly deleted or anonymised.',

        'section_5_heading' => '5. Recipients (subprocessors)',
        'section_5_body' => 'Data may be entrusted to: Hetzner Online GmbH (hosting, DE/EU), OVH SAS (backups, FR/EU), Stripe Payments Europe Ltd. (payments, IE/EU), SMSAPI sp. z o.o. (SMS, PL), transactional email provider (e.g. Postmark/Mailgun — EU/US under SCC). Current list available on request at dpo@hovera.app.',

        'section_6_heading' => '6. User rights',
        'section_6_body' => 'Each data subject has the right to: access (Art. 15 GDPR), rectification (Art. 16), erasure ("right to be forgotten" — Art. 17), restriction of processing (Art. 18), data portability (Art. 20), objection (Art. 21), and to lodge a complaint with the President of the Personal Data Protection Office (PUODO). Submit requests to privacy@hovera.app — response within 30 days.',

        'section_7_heading' => '7. Cookies',
        'section_7_body' => 'The service uses cookies: necessary (session, CSRF, language) — no consent required; analytical (visit count, anonymous statistics) — with consent. Users can manage cookies in browser settings. Disabling necessary cookies prevents panel use.',

        'section_8_heading' => '8. Data security',
        'section_8_body' => 'We apply technical and organisational measures appropriate to the risk: TLS 1.2+ in transit, AES-256 at rest (databases and backups), role-based access control (RBAC), two-factor authentication (2FA) for privileged accounts, daily backups, access logs retained for 12 months, regular security audits.',

        'section_9_heading' => '9. GDPR contact',
        'section_9_body' => 'Data Protection Officer: dpo@hovera.app. Controller: privacy@hovera.app. For B2B Customers processing data of their stable clients — see the Data Processing Agreement (DPA) at /dpa.',
    ],

    'dpa' => [
        'title' => 'Data Processing Agreement (DPA)',
        'intro' => 'This Data Processing Agreement is an integral part of the Terms of Service and is concluded between the Customer (Stable) as Controller of personal data of stable clients (riders, guardians, horse owners) and Sendormeco Holding sp. z o.o. as Processor, in accordance with Art. 28 GDPR.',

        'section_1_heading' => '1. Parties and roles',
        'section_1_body' => 'Controller: the Customer (Stable), entering data of clients, horses and staff into hovera. Processor: Sendormeco Holding sp. z o.o., providing the SaaS service for stable management. Each party is independently responsible for processing within its respective duties.',

        'section_2_heading' => '2. Subject matter and duration',
        'section_2_body' => 'The Processor processes entrusted data solely for and to the extent necessary to provide the hovera service (storage, presentation, backups, notifications). Duration: the term of the main agreement plus a 90-day grace period. After this, data is deleted or returned to the Controller as an export (CSV/JSON) on request.',

        'section_3_heading' => '3. Scope of entrusted data',
        'section_3_body' => 'Categories of data subjects: stable clients (riders, legal guardians of minors, horse owners), stable staff (instructors, grooms). Categories of data: identification (first name, last name), contact (phone, email, address), horse data (name, breed, veterinary documents — as attachments), financial data (settlements), horse medical data (if entered by the stable). Sensitive personal data (rider health, allergies) require separate consent — for which the Controller is responsible.',

        'section_4_heading' => '4. Security measures',
        'section_4_body' => 'The Processor applies: TLS 1.2+ in transit, AES-256 at rest, per-tenant isolation (separate databases), RBAC, 2FA for privileged accounts, daily backups (30-day retention), access and change logs (audit trail) retained for 12 months, regular backup-restore tests, an incident management procedure.',

        'section_5_heading' => '5. Sub-processors',
        'section_5_body' => 'The Controller grants general consent to use sub-processors for hosting and infrastructure. Current list: Hetzner Online GmbH (hosting, DE/EU), OVH SAS (backups, FR/EU), Stripe Payments Europe Ltd. (payments, IE/EU), SMSAPI sp. z o.o. (SMS, PL), transactional email provider (EU/US under SCC). The Processor will notify the Controller by email of planned changes 30 days in advance; the Controller may object.',

        'section_6_heading' => '6. Right to audit',
        'section_6_body' => 'The Controller may audit the Processor\'s compliance with GDPR and this DPA — once per year, on written request 30 days in advance, during the Processor\'s business hours, subject to confidentiality. The Processor may offer a current SOC 2 / ISO 27001 / pen-test report as an alternative to an on-site audit.',

        'section_7_heading' => '7. Breach notification',
        'section_7_body' => 'In the event of a personal data breach, the Processor will notify the Controller without undue delay, no later than 24 hours after detection. The notification includes: nature of the breach, categories and approximate number of affected persons, likely consequences, remedial measures. The Controller is responsible for notifying PUODO within 72 hours (if required).',

        'section_8_heading' => '8. Data return or deletion',
        'section_8_body' => 'Upon termination of the main agreement (after the 90-day grace period), the Processor — at the Controller\'s choice — deletes or returns data (CSV/JSON export, full attachment files in a ZIP archive). The Processor confirms deletion in writing. Backups are overwritten per retention policy (max 30 days).',

        'section_9_heading' => '9. Liability and contractual penalties',
        'section_9_body' => 'The Processor is liable for damages caused by breach of GDPR obligations and this DPA. The parties apply contractual penalties for breach of incident notification duties under Art. 33 GDPR — exact rates in the annex to the main agreement. Liability is limited to fees paid by the Customer in the preceding 12 months, excluding wilful misconduct and supervisory authority fines.',

        'section_10_heading' => '10. Final provisions',
        'section_10_body' => 'Matters not covered are governed by GDPR and Polish law. Amendments require written form (email is sufficient) and acceptance by both parties. Disputes are subject to the court of competent jurisdiction over the Processor\'s registered office.',
    ],
];
