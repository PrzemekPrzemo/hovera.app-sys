<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Invoicing\Owner\OwnerInvoiceFeedService;
use App\Domain\Invoicing\Owner\Snapshots\InvoiceSummarySnapshot;
use App\Models\Central\CentralHorseRegistry;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Owner panel: globalna lista wszystkich faktur (across all stable
 * tenant'ów). Lista snapshot'ów z OwnerInvoiceFeedService, sortowana
 * DESC po issued_at. Pomijamy draft'y.
 *
 * Filter `?horse={centralHorseId}` przełącza na forHorse() — wyniki
 * ograniczone do faktur z invoice_items.horse_id matching koń.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.4" + C.4.
 */
class InvoiceList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.owner.pages.invoice-list';

    protected static ?string $slug = 'invoices';

    /** @var Collection<int, InvoiceSummarySnapshot>|null */
    public ?Collection $invoices = null;

    /**
     * Lista koni ownera dla filter dropdownu — [centralHorseId => name].
     *
     * @var array<string, string>
     */
    public array $horseOptions = [];

    /** Aktualny filtr — null = wszystkie konie. */
    public ?string $horseFilter = null;

    /** Aktualny year filter — null = wszystkie lata. */
    public ?int $yearFilter = null;

    /**
     * Yearly totals — [year => totalCents]. Banner na liście pokazuje
     * sumę dla aktualnie wybranego year filter'a (lub aggregate all
     * years dla "All").
     *
     * @var array<int,int>
     */
    public array $yearlyTotals = [];

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.owner_finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('owner/invoices.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('owner/invoices.list.title');
    }

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $this->horseOptions = CentralHorseRegistry::query()
            ->where('primary_owner_user_id', $user->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $requested = request()->query('horse');
        if (is_string($requested) && isset($this->horseOptions[$requested])) {
            $this->horseFilter = $requested;
        }

        $service = app(OwnerInvoiceFeedService::class);

        // Year filter — przyjmuje tylko reasonable values (2020-2100).
        $yearRaw = request()->query('year');
        if (is_numeric($yearRaw) && (int) $yearRaw >= 2020 && (int) $yearRaw <= 2100) {
            $this->yearFilter = (int) $yearRaw;
        }

        // Yearly totals (zawsze ładujemy — banner pokazuje sumy w
        // chipach roku, niezależnie od aktywnego filtra).
        $this->yearlyTotals = $service->yearlyTotalsForOwner($user);

        // Decyzja źródła listy — priority order: horse + year nie
        // łączymy (jeśli ktoś użyje obu, year wygrywa bo to grubsze
        // filtrowanie historyczne). Można rozszerzyć w przyszłości.
        if ($this->yearFilter !== null) {
            $this->invoices = $service->forOwnerYear($user, $this->yearFilter);
        } elseif ($this->horseFilter !== null) {
            $this->invoices = $service->forHorse($user, $this->horseFilter);
        } else {
            $this->invoices = $service->forOwner($user);
        }
    }

    /**
     * Helper: formatuje cents do "1 234,56 PLN".
     */
    public function formatCents(int $cents, string $currency = 'PLN'): string
    {
        return number_format($cents / 100, 2, ',', ' ').' '.$currency;
    }

    /**
     * Helper: zwraca route URL do InvoiceShow z composite ID.
     */
    public function showUrl(InvoiceSummarySnapshot $invoice): string
    {
        return InvoiceShow::getUrl([
            'stableTenantId' => $invoice->stableTenantId,
            'invoiceId' => $invoice->id,
        ]);
    }

    /**
     * URL do tej samej listy z innym filtr'em (null = wszystkie).
     */
    public function filterUrl(?string $centralHorseId): string
    {
        return $centralHorseId === null
            ? static::getUrl()
            : static::getUrl().'?horse='.$centralHorseId;
    }

    public function activeHorseName(): ?string
    {
        return $this->horseFilter !== null ? ($this->horseOptions[$this->horseFilter] ?? null) : null;
    }

    /**
     * URL do tej samej listy z innym year filter (null = wszystkie lata).
     */
    public function yearFilterUrl(?int $year): string
    {
        return $year === null
            ? static::getUrl()
            : static::getUrl().'?year='.$year;
    }

    /**
     * Łączna suma faktur w aktualnie aktywnym year filter, lub
     * suma wszystkich lat gdy filter = null.
     */
    public function currentYearTotal(): int
    {
        if ($this->yearFilter !== null) {
            return $this->yearlyTotals[$this->yearFilter] ?? 0;
        }

        return array_sum($this->yearlyTotals);
    }

    public function csvExportUrl(): string
    {
        $base = route('owner.invoices.export-csv');

        return $this->yearFilter !== null
            ? $base.'?year='.$this->yearFilter
            : $base;
    }
}
