<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Actions\Calendar\CalendarConflictException;
use App\Actions\Calendar\CreateCalendarEntry;
use App\Actions\Calendar\UpdateCalendarEntry;
use App\Enums\CalendarEntryStatus;
use App\Enums\CalendarEntryType;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\Arena;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Models\Tenant\Instructor;
use App\Services\Calendar\TimetableLoader;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class Calendar extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use RestrictedByTenantRole;

    protected static function allowedRoles(): array
    {
        return TenantRoleGate::HORSE_AND_CARE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.calendar.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.calendar.navigation');
    }

    protected static string $view = 'filament.app.pages.calendar';

    public string $date;

    public string $groupBy = 'instructor';

    public ?string $typeFilter = null;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function todayDay(): void
    {
        $this->date = today()->toDateString();
    }

    /**
     * Renders the day plan for the current props. Called from the Blade
     * view; results are not memoised because Livewire re-renders on
     * every property change anyway.
     *
     * @return array<string,mixed>
     */
    public function getTimetable(): array
    {
        return app(TimetableLoader::class)->loadDay(
            date: Carbon::parse($this->date),
            groupBy: $this->groupBy,
            typeFilter: $this->typeFilter,
        );
    }

    /**
     * Pasek z zawodami LiveJumping na bieżący dzień + następne 7 dni —
     * renderowany na górze widoku kalendarza TYLKO gdy master admin
     * włączył partnership. Zwraca pustą listę gdy integracja OFF lub
     * gdy LJ nie ma zawodów w okresie — wtedy blade nie pokazuje paska.
     *
     * @return list<array<string,mixed>>
     */
    public function getLiveJumpingEvents(): array
    {
        $gate = app(\App\Services\Integrations\LiveJumping\LiveJumpingFeatureGate::class);
        if (! $gate->enabled()) {
            return [];
        }

        $from = Carbon::parse($this->date);
        $to = $from->copy()->addDays(7);

        return app(\App\Services\Integrations\LiveJumping\LiveJumpingClient::class)
            ->getCompetitions($from, $to);
    }

    /**
     * "Add a booking" — opens the Filament Action modal. Pre-filled
     * starts_at + ends_at are passed via Livewire arguments from the
     * Blade view (`wire:click="mountAction('createEntry', {...})"`)
     * if the user clicks an empty slot; absent, defaults to "now + 1h".
     */
    public function createEntryAction(): Action
    {
        return Action::make('createEntry')
            ->label(__('app/calendar_widget.action.create.label'))
            ->modalHeading(__('app/calendar_widget.action.create.modal_heading'))
            ->form($this->entryFormSchema())
            ->fillForm(fn (array $arguments) => array_merge([
                'type' => CalendarEntryType::LessonIndividual->value,
                'status' => CalendarEntryStatus::Confirmed->value,
                'starts_at' => $arguments['starts_at'] ?? now()->ceilHour()->format('Y-m-d H:i:00'),
                'ends_at' => $arguments['ends_at']
                    ?? now()->ceilHour()->addHour()->format('Y-m-d H:i:00'),
                'instructor_id' => $arguments['instructor_id'] ?? null,
                'arena_id' => $arguments['arena_id'] ?? null,
                'horse_id' => $arguments['horse_id'] ?? null,
            ], $arguments))
            ->action(function (array $data) {
                try {
                    app(CreateCalendarEntry::class)->execute($data);
                    Notification::make()->success()->title(__('app/calendar_widget.action.create.success'))->send();
                } catch (CalendarConflictException $e) {
                    Notification::make()->danger()->title(__('app/calendar_widget.action.create.conflict_title'))->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * "Edit booking" — argument $arguments['entry_id'] tells us which.
     */
    public function editEntryAction(): Action
    {
        return Action::make('editEntry')
            ->label(__('app/calendar_widget.action.edit.label'))
            ->modalHeading(__('app/calendar_widget.action.edit.modal_heading'))
            ->form($this->entryFormSchema())
            ->fillForm(function (array $arguments) {
                $entry = CalendarEntry::findOrFail($arguments['entry_id']);

                return [
                    'type' => $entry->type->value,
                    'status' => $entry->status->value,
                    'starts_at' => $entry->starts_at->format('Y-m-d H:i:00'),
                    'ends_at' => $entry->ends_at->format('Y-m-d H:i:00'),
                    'horse_id' => $entry->horse_id,
                    'instructor_id' => $entry->instructor_id,
                    'arena_id' => $entry->arena_id,
                    'client_id' => $entry->client_id,
                    'title' => $entry->title,
                    'notes' => $entry->notes,
                    'price_cents' => $entry->price_cents,
                ];
            })
            ->action(function (array $arguments, array $data) {
                $entry = CalendarEntry::findOrFail($arguments['entry_id']);

                try {
                    app(UpdateCalendarEntry::class)->execute($entry, $data);
                    Notification::make()->success()->title(__('app/calendar_widget.action.edit.success'))->send();
                } catch (CalendarConflictException $e) {
                    Notification::make()->danger()->title(__('app/calendar_widget.action.create.conflict_title'))->body($e->getMessage())->persistent()->send();
                }
            });
    }

    /**
     * Quick "cancel" / "delete" wrapped as a confirm action.
     */
    public function deleteEntryAction(): Action
    {
        return Action::make('deleteEntry')
            ->label(__('app/calendar_widget.action.delete.label'))
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $entry = CalendarEntry::findOrFail($arguments['entry_id']);
                $entry->delete();
                Notification::make()->success()->title(__('app/calendar_widget.action.delete.success'))->send();
            });
    }

    /**
     * Shared form schema used by both create and edit modals. Mirrors
     * the CalendarEntryResource form, kept simple here to fit a modal.
     *
     * @return array<int, Component>
     */
    private function entryFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('type')
                    ->label(__('app/calendar_widget.form.label.type'))
                    ->options(CalendarEntryType::options())
                    ->default(CalendarEntryType::LessonIndividual->value)
                    ->required()
                    ->reactive(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->label(__('app/calendar_widget.form.label.starts_at'))->required()->seconds(false),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->label(__('app/calendar_widget.form.label.ends_at'))->required()->seconds(false)->after('starts_at'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('horse_id')
                    ->label(__('app/calendar_widget.form.label.horse'))
                    ->options(fn () => Horse::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresHorse() ?? false),
                Forms\Components\Select::make('instructor_id')
                    ->label(__('app/calendar_widget.form.label.instructor'))
                    ->options(fn () => Instructor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn (Forms\Get $get) => CalendarEntryType::tryFrom((string) $get('type'))?->requiresInstructor() ?? false),
                Forms\Components\Select::make('arena_id')
                    ->label(__('app/calendar_widget.form.label.arena'))
                    ->options(fn () => Arena::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('client_id')
                    ->label(__('app/calendar_widget.form.label.client'))
                    ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('app/calendar_widget.form.label.title')),
                Forms\Components\Select::make('status')
                    ->label(__('app/calendar_widget.form.label.status'))
                    ->options(CalendarEntryStatus::options())
                    ->default(CalendarEntryStatus::Confirmed->value)
                    ->required(),
            ]),
            Forms\Components\Textarea::make('notes')
                ->label(__('app/calendar_widget.form.label.notes'))->rows(2),
        ];
    }
}
