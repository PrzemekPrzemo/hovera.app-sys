<?php

declare(strict_types=1);

namespace App\Domain\Transport\Dashboard;

use App\Enums\QuoteStatus;
use App\Enums\TransportInvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportServiceArea;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\Vehicle;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;

/**
 * Stats dla landing dashboard'u transportera (`/transport`) — per-tenant
 * liczniki dla hero CTA + onboarding checklist. W przeciwieństwie do
 * `TransportDashboardService` (KPI dla widgetów finansowych) tu liczymy
 * "ile mam dzisiaj do zrobienia": nowe zapytania, oferty czekające na
 * decyzję klienta, nieopłacone FV.
 *
 * Wszystko per-tenant — context wziąć z TenantManager. Brak cache —
 * zapytania szybkie (count() na jednym indeksie), Filament dashboard
 * i tak render'uje raz per wejście.
 */
class TransportHomeStatsService
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Counts dla 4 hero CTA cards. Zwraca 0 gdy brak active tenant
     * (defensive — landing strony nie powinno paść).
     *
     * @return array{
     *   unseen_leads:int,
     *   pending_quotes:int,
     *   unpaid_invoices:int,
     *   unpaid_invoices_cents:int,
     * }
     */
    public function heroCounts(): array
    {
        $tenant = $this->tenants->current();
        if (! $tenant instanceof Tenant) {
            return [
                'unseen_leads' => 0,
                'pending_quotes' => 0,
                'unpaid_invoices' => 0,
                'unpaid_invoices_cents' => 0,
            ];
        }

        $unseenLeads = (int) TransportLeadDispatch::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('view_status', 'unseen')
            ->count();

        $today = Carbon::today();
        $pendingQuotes = (int) Quote::query()
            ->where('status', QuoteStatus::Sent->value)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            })
            ->count();

        $unpaidQ = TransportInvoice::query()
            ->whereIn('status', [
                TransportInvoiceStatus::Issued->value,
                TransportInvoiceStatus::Overdue->value,
            ]);

        return [
            'unseen_leads' => $unseenLeads,
            'pending_quotes' => $pendingQuotes,
            'unpaid_invoices' => (int) (clone $unpaidQ)->count(),
            'unpaid_invoices_cents' => (int) (clone $unpaidQ)->sum('total_cents'),
        ];
    }

    /**
     * Onboarding checklist — co transporter jeszcze powinien zrobić zanim
     * marketplace zacznie kierować mu klientów. Wszystkie steps wymagane
     * (LeadDispatcher pomija transportery bez weryfikacji lub bez pojazdów).
     *
     * @return array{
     *   verified:bool,
     *   has_vehicles:bool,
     *   has_drivers:bool,
     *   has_service_areas:bool,
     *   completed_count:int,
     *   total_count:int,
     * }
     */
    public function onboardingChecklist(): array
    {
        $tenant = $this->tenants->current();
        if (! $tenant instanceof Tenant) {
            return [
                'verified' => false,
                'has_vehicles' => false,
                'has_drivers' => false,
                'has_service_areas' => false,
                'completed_count' => 0,
                'total_count' => 4,
            ];
        }

        $verified = $tenant->isVerifiedTransporter();
        $hasVehicles = Vehicle::query()->exists();
        $hasDrivers = Driver::query()->exists();
        $hasServiceAreas = TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->exists();

        $completed = (int) $verified + (int) $hasVehicles + (int) $hasDrivers + (int) $hasServiceAreas;

        return [
            'verified' => $verified,
            'has_vehicles' => $hasVehicles,
            'has_drivers' => $hasDrivers,
            'has_service_areas' => $hasServiceAreas,
            'completed_count' => $completed,
            'total_count' => 4,
        ];
    }
}
