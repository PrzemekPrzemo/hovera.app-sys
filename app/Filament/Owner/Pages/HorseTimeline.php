<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Horses\Timeline\HorseTimelineEntry;
use App\Domain\Horses\Timeline\HorseTimelineFilter;
use App\Domain\Horses\Timeline\HorseTimelineService;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Owner panel: chronologiczna oś czasu wszystkich akcji wykonanych
 * przez stajnię na koniu. Filtrowanie per kind + date range, render
 * przez Blade z ikoną per kind.
 *
 * URL: /owner/horses/{centralHorseId}/timeline
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 2".
 */
class HorseTimeline extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'horses/{centralHorseId}/timeline';

    protected static string $view = 'filament.owner.pages.horse-timeline';

    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    /** @var array<string, mixed> */
    public array $filters = [
        'kinds' => [],
        'from' => null,
        'to' => null,
    ];

    /** @var list<HorseTimelineEntry> */
    public array $entries = [];

    public function getTitle(): string|Htmlable
    {
        return __('owner/horse_timeline.title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            url('/owner/horses/'.$this->centralHorseId.'/details') => __('owner/horse_detail.breadcrumb'),
            __('owner/horse_timeline.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        try {
            $this->assignment = app(HorseOwnerStableAccessGate::class)
                ->authorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/horse_detail.access.denied'));
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        $this->form->fill($this->filters);
        $this->reloadEntries();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Forms\Components\Section::make(__('owner/horse_timeline.filter.heading'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\CheckboxList::make('kinds')
                            ->label(__('owner/horse_timeline.filter.kinds'))
                            ->columnSpanFull()
                            ->columns(3)
                            ->options($this->kindOptions()),
                        Forms\Components\DatePicker::make('from')
                            ->label(__('owner/horse_timeline.filter.from'))
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label(__('owner/horse_timeline.filter.to'))
                            ->native(false),
                    ]),
            ]);
    }

    public function applyFilters(): void
    {
        $this->filters = $this->form->getState();
        $this->reloadEntries();
    }

    public function resetFilters(): void
    {
        $this->filters = ['kinds' => [], 'from' => null, 'to' => null];
        $this->form->fill($this->filters);
        $this->reloadEntries();
    }

    /**
     * Re-fetch entries z service'u używając aktualnych filtrów.
     */
    private function reloadEntries(): void
    {
        if ($this->stableTenant === null) {
            $this->entries = [];

            return;
        }

        $filter = new HorseTimelineFilter(
            kinds: is_array($this->filters['kinds'] ?? null)
                ? array_values(array_filter($this->filters['kinds'], fn ($k) => in_array($k, HorseTimelineEntry::ALL_KINDS, true)))
                : [],
            from: ! empty($this->filters['from']) ? Carbon::parse((string) $this->filters['from']) : null,
            to: ! empty($this->filters['to']) ? Carbon::parse((string) $this->filters['to'])->endOfDay() : null,
        );

        $this->entries = app(HorseTimelineService::class)
            ->forHorse($this->centralHorseId, $this->stableTenant, $filter);
    }

    /**
     * Helper UI: nazwa ikony heroicon per kind (dla x-icon w blade).
     */
    public function iconFor(string $kind): string
    {
        return match ($kind) {
            HorseTimelineEntry::KIND_HEALTH => 'heroicon-o-heart',
            HorseTimelineEntry::KIND_BOX => 'heroicon-o-home',
            HorseTimelineEntry::KIND_WEIGHT => 'heroicon-o-scale',
            HorseTimelineEntry::KIND_ACTIVITY => 'heroicon-o-sparkles',
            HorseTimelineEntry::KIND_PHOTO => 'heroicon-o-photo',
            HorseTimelineEntry::KIND_DOCUMENT => 'heroicon-o-document-text',
            default => 'heroicon-o-clock',
        };
    }

    /**
     * @return array{badge_bg: string, badge_text: string, icon_text: string, ring_bg: string}
     *         Tailwind classes per kind (hardcoded — JIT compiler musi je
     *         widzieć w source, dlatego nie konkatenujemy `bg-{$color}-100`).
     */
    public function classesFor(string $kind): array
    {
        return match ($kind) {
            HorseTimelineEntry::KIND_HEALTH => [
                'badge_bg' => 'bg-rose-50 dark:bg-rose-900/40',
                'badge_text' => 'text-rose-700 dark:text-rose-300',
                'icon_text' => 'text-rose-700 dark:text-rose-300',
                'ring_bg' => 'bg-rose-100 dark:bg-rose-900/30',
            ],
            HorseTimelineEntry::KIND_BOX => [
                'badge_bg' => 'bg-amber-50 dark:bg-amber-900/40',
                'badge_text' => 'text-amber-700 dark:text-amber-300',
                'icon_text' => 'text-amber-700 dark:text-amber-300',
                'ring_bg' => 'bg-amber-100 dark:bg-amber-900/30',
            ],
            HorseTimelineEntry::KIND_WEIGHT => [
                'badge_bg' => 'bg-sky-50 dark:bg-sky-900/40',
                'badge_text' => 'text-sky-700 dark:text-sky-300',
                'icon_text' => 'text-sky-700 dark:text-sky-300',
                'ring_bg' => 'bg-sky-100 dark:bg-sky-900/30',
            ],
            HorseTimelineEntry::KIND_ACTIVITY => [
                'badge_bg' => 'bg-emerald-50 dark:bg-emerald-900/40',
                'badge_text' => 'text-emerald-700 dark:text-emerald-300',
                'icon_text' => 'text-emerald-700 dark:text-emerald-300',
                'ring_bg' => 'bg-emerald-100 dark:bg-emerald-900/30',
            ],
            HorseTimelineEntry::KIND_PHOTO => [
                'badge_bg' => 'bg-violet-50 dark:bg-violet-900/40',
                'badge_text' => 'text-violet-700 dark:text-violet-300',
                'icon_text' => 'text-violet-700 dark:text-violet-300',
                'ring_bg' => 'bg-violet-100 dark:bg-violet-900/30',
            ],
            HorseTimelineEntry::KIND_DOCUMENT => [
                'badge_bg' => 'bg-gray-100 dark:bg-gray-800',
                'badge_text' => 'text-gray-700 dark:text-gray-300',
                'icon_text' => 'text-gray-700 dark:text-gray-300',
                'ring_bg' => 'bg-gray-200 dark:bg-gray-700',
            ],
            default => [
                'badge_bg' => 'bg-gray-100 dark:bg-gray-800',
                'badge_text' => 'text-gray-700 dark:text-gray-300',
                'icon_text' => 'text-gray-700 dark:text-gray-300',
                'ring_bg' => 'bg-gray-200 dark:bg-gray-700',
            ],
        };
    }

    /**
     * Helper Blade: subkind label z fallback'iem na sam subkind value gdy
     * translation key nie istnieje (np. legacy data po updacie enum).
     */
    public function subkindLabel(string $kind, string $subkind): string
    {
        $key = 'owner/horse_timeline.subkind.'.$kind.'.'.$subkind;
        $translated = __($key);

        return $translated === $key ? $subkind : $translated;
    }

    public function formatCents(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return number_format($cents / 100, 2, ',', ' ').' PLN';
    }

    /** @return array<string, string> */
    private function kindOptions(): array
    {
        $out = [];
        foreach (HorseTimelineEntry::ALL_KINDS as $kind) {
            $out[$kind] = __('owner/horse_timeline.kind.'.$kind);
        }

        return $out;
    }
}
