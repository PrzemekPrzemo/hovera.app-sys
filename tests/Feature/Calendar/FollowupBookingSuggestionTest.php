<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Filament\App\Resources\CalendarEntryResource\Pages\EditCalendarEntry;
use App\Models\Tenant\CalendarEntry;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PR C — Auto-suggest "Zapisz następną lekcję" po complete. Pokrywa
 * decyzję "czy proponować kolejną" + budowę URL z prefilled query string.
 * Test bezpośrednio na `buildFollowupSuggestion()` — Livewire stack
 * pomijamy, bo decyzja jest pure-function w stosunku do entry +
 * previousStatus.
 */
class FollowupBookingSuggestionTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbPath = tempnam(sys_get_temp_dir(), 'hov_followup_').'.sqlite';
        touch($this->dbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->dbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('calendar_entries', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->string('status', 32);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('title')->nullable();
            $t->text('notes')->nullable();
            $t->integer('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamps();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        parent::tearDown();
    }

    public function test_returns_null_when_status_did_not_become_completed(): void
    {
        $entry = $this->makeEntry(status: CalendarEntryStatus::Confirmed);

        $this->assertNull(EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Requested));
    }

    public function test_returns_null_when_already_completed(): void
    {
        $entry = $this->makeEntry(status: CalendarEntryStatus::Completed);

        // completed → completed (np. user edited notes only) — nie chcemy
        // dwóch toastów przy każdym save.
        $this->assertNull(EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Completed));
    }

    public function test_returns_null_for_non_cyclic_types(): void
    {
        foreach ([CalendarEntryType::Care, CalendarEntryType::Event, CalendarEntryType::Block] as $type) {
            $entry = $this->makeEntry(status: CalendarEntryStatus::Completed, type: $type);
            $this->assertNull(
                EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Confirmed),
                "type {$type->value} should not trigger followup suggestion"
            );
        }
    }

    public function test_returns_suggestion_for_lesson_transition_to_completed(): void
    {
        $entry = $this->makeEntry(
            status: CalendarEntryStatus::Completed,
            type: CalendarEntryType::LessonIndividual,
        );

        $suggestion = EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Confirmed);

        $this->assertNotNull($suggestion);
        $this->assertSame(
            $entry->starts_at->copy()->addDays(7)->toIso8601String(),
            $suggestion['starts_at']->toIso8601String(),
            'suggested starts_at = original + 7d',
        );
        $this->assertSame(
            $entry->ends_at->copy()->addDays(7)->toIso8601String(),
            $suggestion['ends_at']->toIso8601String(),
            'suggested ends_at = original + 7d',
        );
    }

    public function test_url_carries_horse_instructor_arena_client_and_dates(): void
    {
        $entry = $this->makeEntry(
            status: CalendarEntryStatus::Completed,
            type: CalendarEntryType::LessonIndividual,
            horseId: '01HHORSE0000000000000ABC1',
            instructorId: '01HINSTR0000000000000ABC2',
            arenaId: '01HAREN00000000000000ABC3',
            clientId: '01HCLNT00000000000000ABC4',
        );

        $suggestion = EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Confirmed);

        $this->assertNotNull($suggestion);
        $url = $suggestion['url'];
        $this->assertStringContainsString('horse_id=01HHORSE0000000000000ABC1', $url);
        $this->assertStringContainsString('instructor_id=01HINSTR0000000000000ABC2', $url);
        $this->assertStringContainsString('arena_id=01HAREN00000000000000ABC3', $url);
        $this->assertStringContainsString('client_id=01HCLNT00000000000000ABC4', $url);
        $this->assertStringContainsString('type=lesson_individual', $url);
        $this->assertStringContainsString('starts_at=', $url);
        $this->assertStringContainsString('ends_at=', $url);
    }

    public function test_url_omits_null_resources(): void
    {
        // Lesson group często nie ma horse_id na poziomie entry (jest na
        // participants). URL nie powinien mieć "horse_id=" gdy pole było null.
        $entry = $this->makeEntry(
            status: CalendarEntryStatus::Completed,
            type: CalendarEntryType::LessonGroup,
            horseId: null,
            instructorId: '01HINSTR0000000000000ABC2',
            arenaId: null,
            clientId: null,
        );

        $suggestion = EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Confirmed);

        $this->assertNotNull($suggestion);
        $this->assertStringNotContainsString('horse_id=', $suggestion['url']);
        $this->assertStringNotContainsString('arena_id=', $suggestion['url']);
        $this->assertStringNotContainsString('client_id=', $suggestion['url']);
        $this->assertStringContainsString('instructor_id=01HINSTR0000000000000ABC2', $suggestion['url']);
    }

    public function test_training_type_also_triggers_followup(): void
    {
        $entry = $this->makeEntry(
            status: CalendarEntryStatus::Completed,
            type: CalendarEntryType::Training,
        );

        $this->assertNotNull(
            EditCalendarEntry::buildFollowupSuggestion($entry, CalendarEntryStatus::Confirmed),
            'training is a cyclic activity — should trigger followup',
        );
    }

    private function makeEntry(
        CalendarEntryStatus $status,
        CalendarEntryType $type = CalendarEntryType::LessonIndividual,
        ?string $horseId = '01HHORSE0000000000000DEF1',
        ?string $instructorId = '01HINSTR0000000000000DEF2',
        ?string $arenaId = null,
        ?string $clientId = null,
    ): CalendarEntry {
        $entry = new CalendarEntry;
        $entry->forceFill([
            'id' => '01HENTRY00000000000000'.bin2hex(random_bytes(2)),
            'type' => $type->value,
            'status' => $status->value,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'horse_id' => $horseId,
            'instructor_id' => $instructorId,
            'arena_id' => $arenaId,
            'client_id' => $clientId,
        ])->save();

        return $entry->refresh();
    }
}
