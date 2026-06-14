<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Tenant\BoxInquiry;
use App\Notifications\Stable\BoxInquiryReceivedNotification;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Publiczny formularz "Zapytaj o boks" — bez auth. Trafia z embed widget'a
 * (target=_blank) lub z public micro-site (/s/{slug}). Tworzy
 * `BoxInquiry` w tenant DB + powiadamia owner'a stajni.
 *
 * Rate limit per IP w routes/web.php (`throttle:5,60` = 5 zgłoszeń/h).
 *
 * Antispam: prosty honeypot field (`company`) — boty wypełniają wszystkie
 * pola; gdy honeypot ma wartość → silent 200 + status=spam.
 */
class BoxInquiryController extends Controller
{
    public function form(string $slug, TenantManager $tenants): View|RedirectResponse
    {
        $tenant = $this->resolveTenant($slug, $tenants);

        return view('public.box-inquiry.form', [
            'tenant' => $tenant,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#A8956B'),
            'source' => request()->query('source') === 'embed' ? 'embed' : 'public_site',
        ]);
    }

    public function submit(Request $request, string $slug, TenantManager $tenants): RedirectResponse
    {
        $tenant = $this->resolveTenant($slug, $tenants);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'horse_count' => ['required', 'integer', 'min:1', 'max:50'],
            'preferred_from' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in([BoxInquiry::SOURCE_EMBED, BoxInquiry::SOURCE_PUBLIC_SITE])],
            // Honeypot — bot wypełni wszystko, człowiek nie widzi pola.
            'company' => ['nullable', 'string', 'max:255'],
        ]);

        $isSpam = ! empty($data['company']);

        $inquiry = BoxInquiry::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'horse_count' => $data['horse_count'],
            'preferred_from' => $data['preferred_from'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => $isSpam ? BoxInquiry::STATUS_SPAM : BoxInquiry::STATUS_NEW,
            'source' => $data['source'] ?? BoxInquiry::SOURCE_PUBLIC_SITE,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if (! $isSpam) {
            try {
                $this->notifyStableOwner($tenant, $inquiry);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('public.box_inquiry.thanks', ['slug' => $slug])
            ->with('inquiry_id', $inquiry->id);
    }

    public function thanks(string $slug, TenantManager $tenants): View
    {
        $tenant = $this->resolveTenant($slug, $tenants);

        return view('public.box-inquiry.thanks', [
            'tenant' => $tenant,
            'primary_color' => data_get($tenant->branding, 'primary_color', '#A8956B'),
        ]);
    }

    private function resolveTenant(string $slug, TenantManager $tenants): Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        if ($tenants->current()?->id !== $tenant->id) {
            $tenants->setCurrent($tenant);
        }

        return $tenant;
    }

    private function notifyStableOwner(Tenant $tenant, BoxInquiry $inquiry): void
    {
        $email = DB::connection('central')
            ->table('tenant_memberships')
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $tenant->id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->value('users.email');

        if (! is_string($email) || $email === '') {
            return;
        }

        Notification::route('mail', $email)->notify(
            new BoxInquiryReceivedNotification($inquiry, $tenant),
        );
    }
}
