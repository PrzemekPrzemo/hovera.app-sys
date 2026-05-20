<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Services\MasterAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Self-service rejestracja Horse Owner'a — uproszczony flow w stosunku do
 * stable/transporter signup'u:
 *   - brak slug'a (owner nie ma "firmy" — slug auto-generujemy z emaila)
 *   - brak wyboru planu (zawsze owner_free)
 *   - brak country/locale/timezone selectorów (defaulty PL)
 *   - tylko 3 pola: name, email, telefon (opcjonalny)
 *
 * Throttle 5 rejestracji na godzinę z IP — wyższy niż stable (3/h) bo
 * owner'y to consumer side i większy ruch jest spodziewany.
 *
 * Opcjonalny `?stable={ulid}&token={hex}` parametr = invitation flow ze
 * stajni. Po zarejestrowaniu owner zostaje auto-powiązany z konkretną
 * stajnią (boarding relacja → PR 5).
 */
class HorseOwnerRegistrationController extends Controller
{
    public function show(Request $request): View
    {
        return view('public.horse-owner-registration.form', [
            'old' => [
                'owner_name' => (string) old('owner_name', $request->query('name', '')),
                'owner_email' => (string) old('owner_email', $request->query('email', '')),
                'owner_phone' => (string) old('owner_phone', $request->query('phone', '')),
            ],
            'invite_stable_id' => $request->query('stable'),
            'invite_token' => $request->query('token'),
        ]);
    }

    public function submit(Request $request, CreateTenant $action, MasterAuditLogger $audit): RedirectResponse
    {
        $data = $this->validate($request);

        // Slug auto-generujemy z emaila (np. jan.kowalski@example.com →
        // jan-kowalski + losowe suffix dla uniqueness). Owner nie podaje
        // slug'a — to nie firma, nie szuka go nikt po nazwie.
        $emailLocal = Str::before($data['owner_email'], '@');
        $slugBase = Str::slug($emailLocal) ?: 'owner';
        $slug = $slugBase.'-'.Str::lower(Str::random(6));

        try {
            $tenant = $action->execute([
                'slug' => $slug,
                'name' => $data['owner_name'],
                'type' => TenantType::HorseOwner->value,
                'country' => 'PL',
                'locale' => 'pl',
                'timezone' => 'Europe/Warsaw',
                'currency' => 'PLN',
                'owner_email' => $data['owner_email'],
                'owner_name' => $data['owner_name'],
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors(['signup' => __('public/horse_owner_registration.errors.provisioning_failed')])
                ->withInput();
        }

        // ToS (uproszczony — owner free tier, nie ma billing'u więc też
        // mniej legal surface, ale w razie sporu sąd potrzebuje dowodu
        // zgody).
        $termsVersion = (string) config('hovera.legal.terms_version', '2026-05');
        $tenant->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => $termsVersion,
        ])->save();

        $audit->record(
            action: 'tenant.terms_accepted',
            targetType: 'Tenant',
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: [
                'version' => $termsVersion,
                'tenant_type' => TenantType::HorseOwner->value,
                'owner_email' => $data['owner_email'],
                'owner_phone' => $data['owner_phone'] ?? null,
                'invite_stable_id' => $request->input('invite_stable_id'),
            ],
        );

        return redirect()->route('register.horse-owner.thanks', ['slug' => $tenant->slug]);
    }

    public function thanks(string $slug): View
    {
        return view('public.horse-owner-registration.thanks', [
            'slug' => $slug,
        ]);
    }

    /** @return array{owner_name:string, owner_email:string, owner_phone:?string} */
    private function validate(Request $request): array
    {
        return $request->validate([
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_email' => ['required', 'email:rfc,strict', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:40'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('public/horse_owner_registration.errors.terms'),
        ]);
    }
}
