<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Tenants\CreateTenant;
use App\Domain\Transport\Verification\DocumentUploadService;
use App\Enums\TenantType;
use App\Enums\TransporterDocumentType;
use App\Mail\MasterAdmin\TransporterOnboardingSubmittedMail;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Public transporter onboarding — pełny multi-section signup dla firm
 * transportowych. W przeciwieństwie do lean `/signup?type=transporter`
 * (4 pola), ten flow zbiera w jednym kroku:
 *
 *   1. Firma: nazwa, slug, NIP, REGON, adres
 *   2. Kontakt owner: imię, email, telefon
 *   3. Dokumenty (6 plików): licencja zawodu, PWL T1, PWL T2, certyfikat
 *      kierowcy, świadectwo pojazdu, OC przewoźnika
 *   4. Akceptacja regulaminu marketplace
 *
 * Po submit:
 *   - `CreateTenant` tworzy tenant z type=transporter + status=provisioning
 *     + verification_status=pending
 *   - `TenantManager::setCurrent($tenant)` ustawia kontekst tenant DB
 *   - `DocumentUploadService` zapisuje 6 dokumentów do storage transporter
 *     + tworzy wpisy w transporter_documents (status=pending)
 *   - Email do master admin "nowy transporter X czeka na weryfikację"
 *   - Redirect na thanks page z instrukcją "Sprawdzimy w 2-3 dni roboczych"
 *
 * Patrz docs/TRANSPORT.md §15 (verification flow).
 *
 * Security:
 *   - Throttle 1/h/IP — anti-abuse dla document uploads (6 * 5MB = 30MB).
 *   - File type whitelist (pdf/jpg/png), max 5MB per file.
 *   - Honeypot `website` field — silent 200 dla botów.
 *   - Slug uniqueness check.
 */
class TransporterOnboardingController extends Controller
{
    /**
     * Maks rozmiar per dokument (KB). DocumentUploadService allowi 10MB
     * dla post-login uploadów, public form ścislejszy żeby ograniczyć
     * spam storage'u: 5MB * 6 plików = 30MB per signup max.
     */
    private const MAX_FILE_KB = 5120;

    /**
     * 6 typów dokumentów wymaganych dla pełnej rejestracji. Mapowanie
     * file-input name → TransporterDocumentType. Każdy plik MUSI być
     * wgrany na public form (bez tego admin nie ma czego weryfikować).
     *
     * Wzorzec ze `TransporterDocumentType::pwlRequiredCases()` ale
     * eksplicitnie listowany żeby zmiany enum'a nie zaskakiwały public
     * form'a (controlled scope).
     */
    private const REQUIRED_DOCUMENTS = [
        'doc_road_carrier_license' => TransporterDocumentType::RoadCarrierLicense,
        'doc_pwl_t1' => TransporterDocumentType::PwlAuthorizationT1,
        'doc_pwl_t2' => TransporterDocumentType::PwlAuthorizationT2,
        'doc_pwl_driver_handler' => TransporterDocumentType::PwlDriverHandlerCertificate,
        'doc_pwl_vehicle_approval' => TransporterDocumentType::PwlVehicleApprovalCertificate,
        'doc_wash_disinfection' => TransporterDocumentType::WashDisinfectionLog,
        'doc_carrier_liability' => TransporterDocumentType::CarrierLiabilityInsurance,
    ];

    public function show(Request $request): View
    {
        return view('public.transport.onboarding', [
            'requiredDocuments' => self::REQUIRED_DOCUMENTS,
            'old' => [
                'name' => (string) old('name', $request->query('name', '')),
                'slug' => (string) old('slug', ''),
                'tax_id' => (string) old('tax_id', ''),
                'regon' => (string) old('regon', ''),
                'address' => (string) old('address', ''),
                'owner_name' => (string) old('owner_name', ''),
                'owner_email' => (string) old('owner_email', ''),
                'owner_phone' => (string) old('owner_phone', ''),
            ],
        ]);
    }

    public function submit(
        Request $request,
        CreateTenant $createTenant,
        DocumentUploadService $documentUpload,
        TenantManager $tenants,
        MasterAuditLogger $audit,
    ): RedirectResponse {
        // Honeypot — silent return na thanks page (bot nie wie że został wycięty).
        if ((string) $request->input('website', '') !== '') {
            return redirect()->route('public.transport.onboarding.show')->with(
                'status',
                __('public/transporter_onboarding.notify.thanks_silent'),
            );
        }

        $data = $this->validate($request);

        try {
            $tenant = $createTenant->execute([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'type' => TenantType::Transporter->value,
                'country' => 'PL',
                'locale' => 'pl',
                'timezone' => 'Europe/Warsaw',
                'currency' => 'PLN',
                'owner_email' => $data['owner_email'],
                'owner_name' => $data['owner_name'],
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors(['signup' => __('public/transporter_onboarding.errors.provisioning_failed')])
                ->withInput();
        }

        // Zapisz dodatkowe pola firmowe (NIP/REGON/adres/telefon) na tenant
        // — lean CreateTenant nie obsługuje ich, ale są częścią rejestracji.
        $tenant->forceFill([
            'tax_id' => $data['tax_id'],
            'legal_name' => $data['name'],
            // Persist proof of marketplace ToS acceptance (handover §2 batch 2A).
            'terms_accepted_at' => now(),
            'terms_version' => (string) config('hovera.legal.terms_version', '2026-05'),
            'settings' => array_replace((array) $tenant->settings, [
                'company' => [
                    'regon' => $data['regon'],
                    'address' => $data['address'],
                ],
                'contact' => [
                    'phone' => $data['owner_phone'],
                ],
            ]),
        ])->save();

        // Switch do tenant DB żeby DocumentUploadService mógł zapisać
        // TransporterDocument rows + putFileAs w storage tenant'a.
        $tenants->setCurrent($tenant);

        $uploadedCount = 0;
        foreach (self::REQUIRED_DOCUMENTS as $inputName => $type) {
            $file = $request->file($inputName);
            if (! $file instanceof UploadedFile) {
                continue; // validation już to złapała ale defensive
            }

            try {
                $documentUpload->upload($file, $type);
                $uploadedCount++;
            } catch (Throwable $e) {
                // Soft-fail per dokument — log ale nie roluj tenanta.
                // Owner może douploadować przez panel po login (TransporterDocuments page).
                Log::warning('Transporter onboarding document upload failed', [
                    'tenant_id' => $tenant->id,
                    'document_type' => $type->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Audit log w central — sąd / RODO inspector żądania.
        $audit->record(
            action: 'transporter.onboarding_submitted',
            targetType: 'Tenant',
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: [
                'name' => $tenant->name,
                'tax_id' => $data['tax_id'],
                'owner_email' => $data['owner_email'],
                'documents_uploaded' => $uploadedCount,
                'documents_required' => count(self::REQUIRED_DOCUMENTS),
            ],
        );

        // Email do master admin'ów (gdzieś defined w config('hovera.master_admin_emails')
        // lub fallback do users WHERE is_master_admin=true).
        try {
            $recipients = $this->resolveMasterAdminEmails();
            if ($recipients !== []) {
                Mail::to($recipients)->send(new TransporterOnboardingSubmittedMail(
                    tenant: $tenant->fresh(),
                    documentsUploaded: $uploadedCount,
                    documentsRequired: count(self::REQUIRED_DOCUMENTS),
                ));
            }
        } catch (Throwable $e) {
            // Soft-fail — signup poszło, admin notification jest nice-to-have.
            // Master admin i tak zobaczy w `/admin/tenants` filter
            // `verification_status=pending`.
            Log::warning('Transporter onboarding admin notification failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('public.transport.onboarding.thanks', ['slug' => $tenant->slug]);
    }

    public function thanks(Request $request, string $slug): View
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

        return view('public.transport.onboarding-thanks', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * @return array{
     *   name:string, slug:string, tax_id:string, regon:string, address:string,
     *   owner_name:string, owner_email:string, owner_phone:string,
     * }
     */
    private function validate(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'slug' => [
                'required', 'string', 'min:3', 'max:62',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,60}[a-z0-9])?$/',
                Rule::unique('central.tenants', 'slug'),
            ],
            'tax_id' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'regon' => ['required', 'string', 'regex:/^[0-9]{9}([0-9]{5})?$/'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_email' => ['required', 'email:rfc,strict', 'max:255'],
            'owner_phone' => ['required', 'string', 'min:7', 'max:40'],
            'terms' => ['accepted'],
        ];

        // Każdy dokument jest required, pdf/jpg/png, max 5MB.
        foreach (self::REQUIRED_DOCUMENTS as $inputName => $_) {
            $rules[$inputName] = [
                'required', 'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.self::MAX_FILE_KB,
            ];
        }

        $data = $request->validate($rules, [
            'slug.regex' => __('public/transporter_onboarding.errors.slug_format'),
            'slug.unique' => __('public/transporter_onboarding.errors.slug_taken'),
            'tax_id.regex' => __('public/transporter_onboarding.errors.tax_id_format'),
            'regon.regex' => __('public/transporter_onboarding.errors.regon_format'),
            'terms.accepted' => __('public/transporter_onboarding.errors.terms'),
        ]);

        $data['slug'] = Str::lower($data['slug']);

        // strip non-digit characters z NIP/REGON dla normalizacji
        $data['tax_id'] = preg_replace('/\D/', '', (string) $data['tax_id']) ?: '';
        $data['regon'] = preg_replace('/\D/', '', (string) $data['regon']) ?: '';

        return $data;
    }

    /**
     * @return list<string>
     */
    private function resolveMasterAdminEmails(): array
    {
        // Try users.is_master_admin=true. Fallback do config legal contact.
        $emails = DB::connection('central')
            ->table('users')
            ->where('is_master_admin', true)
            ->whereNull('deleted_at')
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if ($emails === []) {
            $fallback = (string) config('hovera.legal.contact_email', '');

            return $fallback !== '' ? [$fallback] : [];
        }

        return $emails;
    }
}
