<?php

declare(strict_types=1);

return [
    'last_updated' => '2026-05-18',
    'last_updated_label' => 'Last updated',

    'nav' => [
        'pricing' => 'Pricing',
        'demo' => 'Demo',
        'signup' => 'Create stable',
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'dpa' => 'DPA',
        'marketplace' => 'Transport marketplace',
    ],

    'footer' => [
        'copyright' => '© :year hovera. All rights reserved.',
        'support' => 'Support: office@hovera.app',
    ],

    'terms' => [
        'title' => 'hovera Terms of Service',
        'intro' => 'These Terms govern access to and use of the hovera service available at app.hovera.app, provided by Sendormeco Holding sp. z o.o. (the "Provider") to businesses operating riding stables, transport companies and related activities (the "Customer"). Hovera provides EXCLUSIVELY technology services (SaaS). For the transport module, Hovera acts as a MARKETPLACE INTERMEDIARY between customers and independent transport providers and IS NOT a transport carrier nor a party to the transport contract — details in the separate <a href="/regulamin-marketplace">Transport Marketplace Terms</a>.',

        'section_1_heading' => '1. Definitions',
        'section_1_body' => 'Service — the hovera SaaS platform for stable management (bookings, clients, horses, billing, communications). Customer — the business that has entered into a service agreement. User — a natural person using the service on behalf of the Customer (owner, instructor, employee, rider). Trial — the 30-day free period from registration. Plan — a feature package described on the pricing page.',

        'section_2_heading' => '2. Scope of service',
        'section_2_body' => 'The Service includes: lesson calendar, client and horse records, billing (invoices, online payments), public stable micro-site, online booking form, customer communications (email, SMS), financial reports, transport fleet management, transport inquiry marketplace and related features. The full feature list per Plan is available at /pricing. For the transport module (marketplace of inquiries from stables to transport companies), the Provider acts EXCLUSIVELY as a technology intermediary — it does not perform transports, does not own vehicles, does not employ drivers, and is not a party to the transport contract. Detailed marketplace rules are governed by the separate <a href="/regulamin-marketplace">Transport Marketplace Terms</a>, which form an integral part of these Terms for Customers using the transport module.',

        'section_3_heading' => '3. Registration and contract formation',
        'section_3_body' => 'Registration occurs via /signup. The Customer provides stable name, stable URL, owner contact details, and accepts these Terms and the privacy policy. The agreement is formed when the Provider creates the tenant and sends an activation link to the provided email. The Customer warrants the accuracy of submitted data.',

        'section_4_heading' => '4. Trial period',
        'section_4_body' => 'Each new Customer receives a 30-day free trial with full Pro plan functionality. After 30 days, panel access is restricted to "read-only" until a plan is selected and paid for. The trial does NOT auto-convert to a paid subscription — the Customer chooses when to start payment.',

        'section_5_heading' => '5. Fees and payments',
        'section_5_body' => 'Fees and plan scope are set out in the current pricing page at /pricing. Fees are charged in advance per billing period (monthly or annual) via the payment provider (Stripe). VAT invoices are issued electronically and emailed to the Customer. Late payment of more than 14 days entitles the Provider to suspend panel access (with data retention of 90 days from suspension).',

        'section_6_heading' => '6. Complaints',
        'section_6_body' => 'Complaints should be submitted to office@hovera.app within 14 days of the event. The complaint should include Customer details, problem description, and the desired resolution. The Provider will respond within 14 working days. No response within the deadline is treated as acceptance of the complaint.',

        'section_7_heading' => '7. Availability (SLA)',
        'section_7_body' => 'The Provider commits to 99.5% monthly availability, excluding planned maintenance windows announced 48 hours in advance. If the unavailability threshold is exceeded, the Customer is entitled to a credit proportional to downtime (per the SLA schedule available on request).',

        'section_8_heading' => '8. Liability',
        'section_8_body' => 'The Provider\'s liability for non-performance or improper performance is limited to fees paid by the Customer in the preceding 12 months. The Provider is not liable for lost profits, indirect damages, or harm caused by misuse of the service by the Customer\'s Users. This limitation does not apply to wilful misconduct or breaches of GDPR.',

        'section_9_heading' => '9. Governing law and jurisdiction',
        'section_9_body' => 'Matters not covered by these Terms are governed by Polish law, in particular the Civil Code and the Act on Providing Services by Electronic Means. Disputes are subject to the court of competent jurisdiction over the Provider\'s registered office (Warsaw).',

        'section_10_heading' => '10. Changes to the Terms',
        'section_10_body' => 'The Provider reserves the right to amend these Terms. Changes will be communicated by email at least 30 days in advance. If the Customer does not accept changes, they may terminate the agreement with immediate effect before the changes take effect.',

        'section_11_heading' => '11. Contact',
        'section_11_body' => 'Questions about these Terms: office@hovera.app. Provider details: Sendormeco Holding sp. z o.o., registered office at ul. Złota 75A/7, 00-819 Warsaw, Poland; NIP (Polish tax ID): 5252866457; REGON: 389194801; registered in the National Court Register (KRS) under number 0000906110, kept by the District Court for the Capital City of Warsaw, 12th Commercial Division of KRS; share capital PLN 5,000.',
    ],

    'privacy' => [
        'title' => 'hovera Privacy Policy',
        'intro' => 'This privacy policy describes how personal data of hovera users is processed, in accordance with GDPR (EU 2016/679) and Polish Personal Data Protection Act of 10 May 2018.',

        'section_1_heading' => '1. Data controller',
        'section_1_body' => 'The data controller is Sendormeco Holding sp. z o.o., based in Warsaw, registered office at ul. Złota 75A/7, 00-819 Warsaw, Poland; NIP: 5252866457; REGON: 389194801; KRS: 0000906110. Controller contact: office@hovera.app. Data Protection Officer (DPO): office@hovera.app.',

        'section_2_heading' => '2. Scope of processed data',
        'section_2_body' => 'During registration: full name, email, stable name, stable URL. During use of the service: IP address, session and cookie identifiers, device technical data (user-agent), activity logs (login time, panel actions). Data entered by the Customer about its clients (riders, horses) is processed as a processor — under the DPA terms (/dpa).',

        'section_3_heading' => '3. Purpose and legal basis',
        'section_3_body' => 'We process data to: (a) conclude and perform the service agreement — Art. 6(1)(b) GDPR; (b) issue invoices and tax records — Art. 6(1)(c) GDPR (legal obligation); (c) ensure system security and detect abuse — Art. 6(1)(f) GDPR (legitimate interest); (d) market our own services to existing customers — Art. 6(1)(f) GDPR (legitimate interest), with a right to object.',

        'section_4_heading' => '4. Retention period',
        'section_4_body' => 'Customer account data: for the duration of the agreement plus 90 days (reactivation grace period). Invoice data: 5 years from the end of the fiscal year (tax law obligation). System logs: 12 months. Analytics cookies: 13 months. After the retention period, data is irreversibly deleted or anonymised.',

        'section_5_heading' => '5. Recipients (subprocessors)',
        'section_5_body' => 'Data may be entrusted to: Hetzner Online GmbH (hosting, DE/EU), OVH SAS (backups, FR/EU), Stripe Payments Europe Ltd. (payments, IE/EU), SMSAPI sp. z o.o. (SMS, PL), transactional email provider (e.g. Postmark/Mailgun — EU/US under SCC). Current list available on request at office@hovera.app.',

        'section_6_heading' => '6. User rights',
        'section_6_body' => 'Each data subject has the right to: access (Art. 15 GDPR), rectification (Art. 16), erasure ("right to be forgotten" — Art. 17), restriction of processing (Art. 18), data portability (Art. 20), objection (Art. 21), and to lodge a complaint with the President of the Personal Data Protection Office (PUODO). Submit requests to office@hovera.app — response within 30 days.',

        'section_7_heading' => '7. Cookies',
        'section_7_body' => 'The service uses cookies: necessary (session, CSRF, language) — no consent required; analytical (visit count, anonymous statistics) — with consent. Users can manage cookies in browser settings. Disabling necessary cookies prevents panel use.',

        'section_8_heading' => '8. Data security',
        'section_8_body' => 'We apply technical and organisational measures appropriate to the risk: TLS 1.2+ in transit, AES-256 at rest (databases and backups), role-based access control (RBAC), two-factor authentication (2FA) for privileged accounts, daily backups, access logs retained for 12 months, regular security audits.',

        'section_9_heading' => '9. GDPR contact',
        'section_9_body' => 'Data Protection Officer: office@hovera.app. Controller: office@hovera.app. For B2B Customers processing data of their stable clients — see the Data Processing Agreement (DPA) at /dpa.',

        'section_10_heading' => '10. Transport marketplace — two independent controllers',
        'section_10_body' => 'Within the transport marketplace module (the public inquiry form "/transport/zapytanie" and quote acceptance at "/transport/quote/...") the Customer (the person requesting transport) submits contact details (name, email, phone, pickup and dropoff addresses). After the inquiry is created, Hovera shares this data with selected Transporters from the marketplace to allow them to prepare quotes. Upon Customer acceptance of a quote, the selected Transporter becomes an INDEPENDENT data controller for the Customer\'s data with respect to performing the transport contract — Hovera is not responsible for how the Transporter processes the data after quote acceptance. The Customer addresses GDPR rights requests directly to the Transporter (Transporter contact details are available on the public profile /t/{slug} and on the invoice). Hovera remains controller of the Customer\'s data only for platform operations (inquiry history, anti-spam, marketplace statistics). Details in the <a href="/regulamin-marketplace">Transport Marketplace Terms</a>.',
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

        'section_11_heading' => '11. Transport marketplace — Customer → Hovera → Transporter data flow',
        'section_11_body' => 'This DPA governs entrustment of Stable client data to Hovera. The transport marketplace module follows a separate data flow: personal data of the natural person requesting transport (via "/transport/zapytanie") is collected by Hovera as controller and then shared with the selected Transporter, who becomes an INDEPENDENT controller from the moment of receiving the inquiry (NOT a processor of Hovera). Hovera and the Transporter are NOT joint controllers within the meaning of Art. 26 GDPR — each is independently responsible within its scope. Roles, liabilities and information obligations are described in the <a href="/regulamin-marketplace">Transport Marketplace Terms</a>.',
    ],

    'marketplace' => [
        'title' => 'hovera Transport Marketplace Terms',
        'intro' => 'These Terms govern the use of the hovera transport marketplace — a platform connecting customers (horse owners, stables) with independent transport providers specialising in equine transport. The platform operator is :company. These Terms form an integral part of the hovera Terms of Service (at /regulamin) for Customers and Transporters using the transport module.',

        'section_1_heading' => '1. Definitions',
        'section_1_body' => 'Hovera / Operator — :company, the entity operating the hovera technology platform (registered details in section 10 of these Terms). Customer — a natural or legal person ordering horse transport through the platform (via the form /transport/zapytanie). Transporter — an independent business providing horse transport services, registered in hovera as a "transporter" tenant, verified by the Operator. Inquiry — a request for transport published by the Customer (from/to, date, number of horses). Quote — a Transporter\'s proposal containing price, date and terms, sent to the Customer by email with an acceptance link. Marketplace — the hovera module for routing inquiries to Transporters and collecting quotes.',

        'section_2_heading' => '2. Nature of Hovera\'s service — intermediation, not transport',
        'section_2_body' => 'HOVERA PROVIDES EXCLUSIVELY TECHNOLOGY INTERMEDIATION SERVICES. Hovera IS NOT a transport carrier within the meaning of the Polish Civil Code (art. 774 et seq.) nor the Road Transport Act. Hovera: (a) does not own vehicles and does not rent them to Transporters, (b) does not employ drivers or other transport personnel, (c) does not perform transports nor organise them logistically, (d) IS NOT a party to the transport contract concluded between the Customer and the Transporter, (e) bears no carrier liability for animals during transport. The transport contract is concluded EXCLUSIVELY between the Customer and the Transporter — directly, upon the Customer\'s acceptance of the quote.',

        'section_3_heading' => '3. Hovera\'s role',
        'section_3_body' => 'Hovera provides a technology platform enabling: (a) publication of transport inquiries by Customers, (b) automatic routing of inquiries to Transporters covering the given route (based on their declared service areas), (c) preparation and dispatch of quotes (PDF + acceptance link) by Transporters, (d) acceptance or rejection of quotes by Customers via the public link, (e) email communication between parties, (f) maintenance of public Transporter profiles (/t/{slug}) with contact details and fleet description. For providing the platform, Hovera charges a subscription fee from Transporters per the current pricing (/pricing). Hovera may in the future charge a commission on accepted quotes — if so, this will be clearly communicated to Transporters with 30 days\' notice.',

        'section_4_heading' => '4. Disclaimer of Hovera\'s liability',
        'section_4_body' => 'Hovera IS NOT LIABLE for: (a) the quality, timeliness and safety of transport — liability rests EXCLUSIVELY with the Transporter under the Civil Code, the transport contract and the Transporter\'s carrier insurance policy; (b) the technical condition of vehicles, driver qualifications, possession of required permits (animal transport certificate, transport licence) — Transporter\'s liability; (c) property damage (harm to horse, animal injury, indirect losses) arising from transport — Customer\'s claims are directed to the Transporter directly; (d) payments between Customer and Transporter — Hovera does not intermediate in transport payments; (e) tax consequences on the Transporter\'s side (VAT, PIT, KSeF, JPK) — the Transporter independently settles the transport invoice; (f) the truthfulness of data provided by Customers in inquiries and by Transporters in profiles. Hovera\'s liability for improper performance of intermediation services is limited to subscription fees paid by the Transporter in the preceding 12 months.',

        'section_5_heading' => '5. Verification of Transporters',
        'section_5_body' => 'Before appearing in the marketplace, every Transporter undergoes an initial document verification: (a) CEIDG or KRS registration, (b) NIP (Polish tax ID), (c) animal transport certificate / permit (where required by law), (d) current carrier insurance policy. Verification is performed by the Operator\'s team based on documents uploaded by the Transporter. Positive verification ONLY confirms document completeness — IT DOES NOT GUARANTEE the quality, timeliness or safety of transport services. The Customer is obliged to independently assess the Transporter before accepting a quote (references, public profile, phone contact, external reviews). The Operator reserves the right to suspend or remove a Transporter from the marketplace in case of repeated complaints, breaches of these Terms, or loss of required documents / permits.',

        'section_6_heading' => '6. Acceptance of a quote = contract between Customer and Transporter',
        'section_6_body' => 'Upon clicking "Accept quote" on /transport/quote/{slug}/{token}, the Customer concludes a direct transport contract with the Transporter named in the quote, on the terms (price, date, route, additional conditions) set out in the quote text and the attached PDF. Hovera only technically relays the acceptance to the Transporter and notifies other Transporters that the inquiry is closed (auto-reject). Hovera IS NOT a party to this contract, DOES NOT GUARANTEE its performance, and IS NOT LIABLE for its execution. Customer and Transporter independently settle additional matters (invoice, deposit, cancellation, meeting place and time) — directly via the contact channel given in the quote or on the Transporter\'s profile.',

        'section_7_heading' => '7. Complaints and disputes',
        'section_7_body' => 'All complaints regarding the quality, course or outcome of transport are submitted by the Customer DIRECTLY to the Transporter (Transporter\'s contact details are on the transport invoice and on the public profile /t/{slug}). Hovera does not handle substantive disputes arising from the transport contract. Hovera may — at the request of both parties — voluntarily mediate a dispute, but has no obligation to do so and no authority to impose a resolution. Complaints concerning the technology platform itself (e.g. form bug, broken acceptance link, undelivered email) are submitted to :support_email — Hovera will respond within 14 working days.',

        'section_8_heading' => '8. Personal data — two controllers',
        'section_8_body' => 'Customer personal data (name, email, phone, pickup/dropoff addresses) is processed as follows: (1) Hovera is the data controller for platform operations (inquiry record, anti-spam, statistics, contact history); legal basis: Art. 6(1)(b) GDPR (contract with Customer to use the platform) and (f) (legitimate interest — security). (2) After a Transporter is chosen (quote accepted) the selected Transporter becomes an INDEPENDENT data controller for the Customer\'s data with respect to performance of the transport contract — the Transporter independently fulfils the information obligation under Art. 13 GDPR and is responsible for compliance. (3) Hovera and the Transporter are NOT joint controllers within the meaning of Art. 26 GDPR. Full Hovera privacy policy: /polityka-prywatnosci. DPA between Hovera and the Customer-Stable: /dpa.',

        'section_9_heading' => '9. Changes to the Terms',
        'section_9_body' => 'The Operator reserves the right to amend the marketplace terms. Customers and Transporters will be notified by email at least 14 days before changes take effect. Continued use of the marketplace after the effective date constitutes acceptance. If a party does not accept, they may stop using the marketplace and terminate the platform usage agreement with immediate effect.',

        'section_10_heading' => '10. Final provisions',
        'section_10_body' => 'Matters not covered by these Terms are governed by Polish law, in particular the Civil Code, the Act on Providing Services by Electronic Means, the Road Transport Act and GDPR. Disputes arising from use of the marketplace are subject to the court of competent jurisdiction over the Operator\'s registered office (:court). Operator details: :company, :address, NIP :nip, KRS :krs. These Terms take effect on :effective_date.',
    ],
];
