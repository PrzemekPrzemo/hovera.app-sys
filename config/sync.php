<?php

declare(strict_types=1);

use App\Http\Resources\V1\CalendarEntryResource;
use App\Http\Resources\V1\ClientMessageResource;
use App\Http\Resources\V1\HorseResource;
use App\Http\Resources\V1\InvoiceResource;
use App\Models\Tenant\Arena;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\BoxAssignment;
use App\Models\Tenant\Building;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\CalendarEntryParticipant;
use App\Models\Tenant\Client;
use App\Models\Tenant\ClientMessage;
use App\Models\Tenant\FeedItem;
use App\Models\Tenant\FeedStockMovement;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseDocument;
use App\Models\Tenant\HorseFeedingPlanItem;
use App\Models\Tenant\HorseMessage;
use App\Models\Tenant\HorsePhoto;
use App\Models\Tenant\HorseWeightMeasurement;
use App\Models\Tenant\Instructor;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Pass;
use App\Models\Tenant\PassUse;
use App\Models\Tenant\Payment;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Models\Tenant\Specialist;
use App\Models\Tenant\StableActivity;
use App\Models\Tenant\TreatmentTemplate;
use App\Services\Sync\Handlers\CalendarEntryMutationHandler;
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
            'model' => Horse::class,
            'resource' => HorseResource::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_photos' => [
            'model' => HorsePhoto::class,
            'mutate_roles' => 'any',
            'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
        ],
        'horse_documents' => [
            'model' => HorseDocument::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_weight_measurements' => [
            'model' => HorseWeightMeasurement::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'horse_feeding_plan_items' => [
            'model' => HorseFeedingPlanItem::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'calendar_entries' => [
            'model' => CalendarEntry::class,
            'resource' => CalendarEntryResource::class,
            'mutate_roles' => ['client', 'instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
            'mutation_handler' => CalendarEntryMutationHandler::class,
        ],
        'recurring_calendar_entries' => [
            'model' => RecurringCalendarEntry::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'calendar_entry_participants' => [
            'model' => CalendarEntryParticipant::class,
            'mutate_roles' => ['instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'arenas' => [
            'model' => Arena::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'buildings' => [
            'model' => Building::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'boxes' => [
            'model' => Box::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'box_assignments' => [
            'model' => BoxAssignment::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'clients' => [
            'model' => Client::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'instructors' => [
            'model' => Instructor::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'specialists' => [
            'model' => Specialist::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'passes' => [
            'model' => Pass::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'pass_uses' => [
            'model' => PassUse::class,
            'mutate_roles' => ['instructor', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'invoices' => [
            'model' => Invoice::class,
            'resource' => InvoiceResource::class,
            'mutate_roles' => null, // read-only from mobile
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'invoice_items' => [
            'model' => InvoiceItem::class,
            'mutate_roles' => null,
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'payments' => [
            'model' => Payment::class,
            'mutate_roles' => null,
            'conflict' => SyncRegistry::CONFLICT_SERVER_ONLY,
        ],
        'health_records' => [
            'model' => HealthRecord::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'treatment_templates' => [
            'model' => TreatmentTemplate::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'boarding_services' => [
            'model' => BoardingService::class,
            'mutate_roles' => ['manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'stable_activities' => [
            'model' => StableActivity::class,
            'mutate_roles' => ['manager', 'groom'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'feed_items' => [
            'model' => FeedItem::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_LWW,
        ],
        'feed_stock_movements' => [
            'model' => FeedStockMovement::class,
            'mutate_roles' => ['groom', 'manager'],
            'conflict' => SyncRegistry::CONFLICT_SERVER_AUTHORITATIVE,
        ],
        'client_messages' => [
            'model' => ClientMessage::class,
            'resource' => ClientMessageResource::class,
            'mutate_roles' => 'any',
            'conflict' => SyncRegistry::CONFLICT_APPEND_ONLY,
        ],
        'horse_messages' => [
            'model' => HorseMessage::class,
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
