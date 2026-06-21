<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Stable',
        'transporter' => 'Transport company',
        'horse_owner' => 'Horse owner',
    ],

    'vehicle_type' => [
        'truck' => 'Vehicle (powered)',
        'trailer' => 'Trailer',
    ],

    'calculation_mode' => [
        'one_way' => 'One-way trip',
        'round_trip' => 'Round trip',
        'return_home' => 'Direct return to base',
    ],

    'fuel_calculation_mode' => [
        'surcharge' => 'Surcharge (only difference over base)',
        'full_cost' => 'Full fuel cost',
    ],

    'quote_status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'withdrawn' => 'Withdrawn',
    ],

    'verification_status' => [
        'pending' => 'Awaiting documents',
        'under_review' => 'Under review',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ],

    'transport_lead_status' => [
        'open' => 'Open',
        'quoted' => 'Quoted',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'transport_invoice_kind' => [
        'fv' => 'Invoice (VAT)',
        'fv_proforma' => 'Pro forma invoice',
        'fv_korekta' => 'Correction invoice',
    ],

    'transport_invoice_status' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'void' => 'Void',
        'cancelled' => 'Cancelled',
    ],

    'transporter_document_type' => [
        'company_registration' => 'Company registration',
        'company_registration_description' => 'Business registry entry (KRS / CEIDG in PL, equivalent elsewhere — PDF or image).',

        // Legacy — deprecated, hidden in new UI but preserved for backward compatibility.
        'animal_transport_cert' => 'Animal transport certificate (legacy)',
        'animal_transport_cert_description' => 'Old EU 1/2005 certificate — replaced by the PLW Vehicle Approval Certificate.',
        'insurance_ocp' => 'Carrier liability insurance (legacy)',
        'insurance_ocp_description' => 'Replaced by the new "Carrier Liability Insurance" entry in the PLW list.',
        'insurance_ocs' => 'Cargo insurance',
        'insurance_ocs_description' => 'Cargo insurance — covers damage to the transported animal. Optional but recommended.',
        'vehicle_registration' => 'Vehicle registration (legacy)',
        'vehicle_registration_description' => 'Replaced by the PLW Vehicle Approval Certificate.',

        // PLW — Polish intra-EU live animal transport regime.
        'road_carrier_license' => 'Road Carrier Profession License',
        'road_carrier_license_description' => 'Issued by the GITD (national road authority) or the local starosta under EU Reg. 1071/2009 and the Polish Road Transport Act of 2001.',
        'pwl_authorization_type1' => 'PLW Carrier Authorization — Type 1 (< 8h)',
        'pwl_authorization_type1_description' => 'PIW (district veterinary inspectorate) authorization for transports up to 8 hours. Pick Type 1 if you only run short journeys.',
        'pwl_authorization_type2' => 'PLW Carrier Authorization — Type 2 (> 8h)',
        'pwl_authorization_type2_description' => 'PIW authorization for long journeys above 8 hours. Also covers Type 1 use cases.',
        'pwl_driver_handler_certificate' => 'PLW Driver and Handler Competence Certificate',
        'pwl_driver_handler_certificate_description' => 'Article 6 of EU Reg. 1/2005 — certificate of competence for drivers and animal handlers. Upload the full team set.',
        'pwl_vehicle_approval_certificate' => 'PLW Vehicle Approval Certificate',
        'pwl_vehicle_approval_certificate_description' => 'EU Reg. 1/2005 article 18 (< 8h) or article 19 (> 8h). Required for every vehicle used for horse transport.',
        'wash_disinfection_log' => 'Washing and Disinfection Log',
        'wash_disinfection_log_description' => 'Required by the Polish Animal Health Protection Act of 2004. Upload current entries from the last 12 months.',
        'carrier_liability_insurance' => 'Carrier Liability Insurance',
        'carrier_liability_insurance_description' => 'Road carrier liability policy. We check the expiry date and guarantee amount.',

        'other' => 'Other document',
        'other_description' => 'Custom — labour inspection certificate, EU community licence, top-up cargo policy, etc.',
    ],

    'boarding_frequency' => [
        'daily' => 'Daily',
        'monthly' => 'Monthly',
        'per_use' => 'Per use',
        'once' => 'One-time',
    ],

    'calendar_entry_status' => [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'no_show' => 'No-show',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Individual lesson',
        'lesson_group' => 'Group lesson',
        'training' => 'Training',
        'care' => 'Care',
        'event' => 'Event / competition',
        'block' => 'Block',
    ],

    'health_record_type' => [
        'vaccination' => 'Vaccination',
        'deworming' => 'Deworming',
        'vet_visit' => 'Vet visit',
        'farrier' => 'Farrier',
        'dentist' => 'Dentist',
        'check_up' => 'Check-up',
        'medication' => 'Medication',
        'other' => 'Other',
    ],

    'horse_document_kind' => [
        'passport' => 'Passport',
        'contract' => 'Contract',
        'insurance' => 'Insurance',
        'vaccine_book' => 'Vaccination book',
        'ownership_proof' => 'Proof of ownership',
        'competition_licence' => 'Competition licence',
        'vet_certificate' => 'Vet certificate',
        'other' => 'Other',
    ],

    'invoice_kind' => [
        'fv' => 'VAT invoice',
        'fv_proforma' => 'Proforma invoice',
        'fv_korekta' => 'Corrective invoice',
        'fv_uproszczona' => 'Simplified invoice',
    ],

    'invoice_status' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'void' => 'Voided',
        'cancelled' => 'Cancelled',
    ],

    'pass_status' => [
        'active' => 'Active',
        'exhausted' => 'Used up',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'payment_provider' => [
        'none' => 'None',
        'stub' => 'Test (sandbox)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'succeeded' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'recurrence_pattern' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    'stable_activity_type' => [
        'feeding' => 'Feeding',
        'grooming' => 'Grooming',
        'turnout' => 'Turnout',
        'exercise' => 'Working with horse',
        'box_cleaning' => 'Box cleaning',
        'transport_event' => 'Transport / event',
        'other' => 'Other',
    ],

    'feeding_meal' => [
        'breakfast' => 'Morning',
        'midday' => 'Midday',
        'evening' => 'Evening',
        'night' => 'Night',
    ],
];
