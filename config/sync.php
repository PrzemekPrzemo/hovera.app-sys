<?php

declare(strict_types=1);

use App\Services\Sync\SyncRegistry;

/**
 * Per-entity sync policy. Drives both the change feed (which tables to scan)
 * and MutationApplier (which mutations are accepted from clients, and how
 * conflicts are detected).
 *
 * mutate_roles: 'any' (any authenticated tenant member), 'manager', 'instructor',
 *               'groom', 'client' or array combination.
 */
return [
    'entities' => [
        'horses' => [
            'model' => \App\Models\Tenant\Horse::class,
            'resource' => \App\Http\Resources\V1\HorseResource::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_photos' => [
            'model' => \App\Models\Tenant\HorsePhoto::class,
            'mutate_roles' => 'any',
            'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
        ],
        'horse_documents' => [
            'model' => \App\Models\Tenant\HorseDocument::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_weight_measurements' => [
            'model' => \App\Models\Tenant\HorseWeightMeasurement::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_feeding_plan_items' => [
            'model' => \App\Models\Tenant\HorseFeedingPlanItem::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'calendar_entries' => [
            'model' => \App\Models\Tenant\CalendarEntry::class,
            'resource' => \App\Http\Resources\V1\CalendarEntryResource::class,
            'mutate_roles' => ['client', 'instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
            'mutation_handler' => \App\Services\Sync\Handlers\CalendarEntryMutationHandler::class,
        ],
        'recurring_calendar_entries' => [
            'model' => \App\Models\Tenant\RecurringCalendarEntry::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'calendar_entry_participants' => [
            'model' => \App\Models\Tenant\CalendarEntryParticipant::class,
            'mutate_roles' => ['instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'arenas' => [
            'model' => \App\Models\Tenant\Arena::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'buildings' => [
            'model' => \App\Models\Tenant\Building::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'boxes' => [
            'model' => \App\Models\Tenant\Box::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'box_assignments' => [
            'model' => \App\Models\Tenant\BoxAssignment::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'clients' => [
            'model' => \App\Models\Tenant\Client::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'instructors' => [
            'model' => \App\Models\Tenant\Instructor::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'specialists' => [
            'model' => \App\Models\Tenant\Specialist::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'passes' => [
            'model' => \App\Models\Tenant\Pass::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'pass_uses' => [
            'model' => \App\Models\Tenant\PassUse::class,
            'mutate_roles' => ['instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'invoices' => [
            'model' => \App\Models\Tenant\Invoice::class,
            'resource' => \App\Http\Resources\V1\InvoiceResource::class,
            'mutate_roles' => null, // read-only from mobile
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'invoice_items' => [
            'model' => \App\Models\Tenant\InvoiceItem::class,
            'mutate_roles' => null,
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'payments' => [
            'model' => \App\Models\Tenant\Payment::class,
            'mutate_roles' => null,
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'health_records' => [
            'model' => \App\Models\Tenant\HealthRecord::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'treatment_templates' => [
            'model' => \App\Models\Tenant\TreatmentTemplate::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'boarding_services' => [
            'model' => \App\Models\Tenant\BoardingService::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'stable_activities' => [
            'model' => \App\Models\Tenant\StableActivity::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'feed_items' => [
            'model' => \App\Models\Tenant\FeedItem::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'feed_stock_movements' => [
            'model' => \App\Models\Tenant\FeedStockMovement::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'client_messages' => [
            'model' => \App\Models\Tenant\ClientMessage::class,
            'resource' => \App\Http\Resources\V1\ClientMessageResource::class,
            'mutate_roles' => 'any',
            'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
        ],
        'horse_messages' => [
            'model' => \App\Models\Tenant\HorseMessage::class,
            'mutate_roles' => 'any',
            'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
        ],
    ],

    'pull' => [
        'max_limit' => 500,
        'default_limit' => 200,
    ],

    'push' => [
        'max_batch' => 100,
        'idempotency_retention_days' => 14,
    ],

    // Recurring calendar entries are expanded server-side into virtual
    // calendar_entries within this rolling window before being returned
    // through the change feed.
    'recurring_window_days' => 60,
];
