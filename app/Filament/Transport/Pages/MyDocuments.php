<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Models\Tenant\Driver;
use App\Services\Tenancy\CurrentDriverResolver;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Driver-only widok: status własnych dokumentów (prawo jazdy, ADR,
 * świadectwo transportu zwierząt). Pokazuje:
 *   - typ dokumentu + numer (jeśli ma)
 *   - data wygaśnięcia
 *   - status: ok / nadchodzi (30 dni) / wygasł
 *
 * NIE pozwala edytować — to robi operator/manager przez DriverResource.
 * Cel: kierowca widzi w jednym miejscu co ma do odnowienia.
 */
class MyDocuments extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.transport.pages.my-documents';

    public ?Driver $driver = null;

    public static function getNavigationLabel(): string
    {
        return __('transport/my_documents.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/my_documents.title');
    }

    public static function canAccess(): bool
    {
        return app(CurrentDriverResolver::class)->current() !== null;
    }

    public function mount(): void
    {
        $this->driver = app(CurrentDriverResolver::class)->current();
    }

    /**
     * @return list<array{key:string, label:string, value:?string, expires_at:?string, status:string}>
     */
    public function getDocuments(): array
    {
        $d = $this->driver;
        if ($d === null) {
            return [];
        }

        $docs = [];

        $docs[] = $this->row(
            key: 'license',
            label: __('transport/my_documents.doc.license'),
            value: $d->license_number,
            expiresAt: $d->license_expires_at?->toDateString(),
        );

        if ($d->has_animal_transport_cert) {
            $docs[] = $this->row(
                key: 'animal_cert',
                label: __('transport/my_documents.doc.animal_cert'),
                value: __('transport/my_documents.doc.value_present'),
                expiresAt: $d->animal_transport_cert_expires_at?->toDateString(),
            );
        }

        if ($d->has_adr) {
            $docs[] = $this->row(
                key: 'adr',
                label: __('transport/my_documents.doc.adr'),
                value: __('transport/my_documents.doc.value_present'),
                expiresAt: $d->adr_expires_at?->toDateString(),
            );
        }

        return $docs;
    }

    /**
     * @return array{key:string, label:string, value:?string, expires_at:?string, status:string}
     */
    private function row(string $key, string $label, ?string $value, ?string $expiresAt): array
    {
        $status = 'ok';
        if ($expiresAt !== null) {
            $days = now()->diffInDays($expiresAt, false);
            if ($days < 0) {
                $status = 'expired';
            } elseif ($days <= 30) {
                $status = 'soon';
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'expires_at' => $expiresAt,
            'status' => $status,
        ];
    }
}
