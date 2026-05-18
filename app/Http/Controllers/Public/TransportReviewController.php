<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Notifications\TransportReviewSubmittedNotification;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\View\View;

/**
 * Publiczny formularz recenzji — magic-link auth (token w URLu = jedyna
 * poświadczeniowa). Patrz docs/TRANSPORT.md §12 + §14.
 *
 *   GET   /transport/review/{token}             → formularz (gwiazdki + komentarz)
 *   POST  /transport/review/{token}             → submit + redirect na thanks
 *   GET   /transport/review/dziekujemy          → strona podziękowań
 *
 * Token TTL = 30 dni od `invite_sent_at`. Token używalny RAZ — po
 * submit'cie kolejny GET pokazuje friendly "Już zostawiłeś opinię"
 * (200, nie 404 — token jest legitymowany, tylko wykonana akcja).
 */
class TransportReviewController extends Controller
{
    public function show(string $token): View|Response
    {
        $review = $this->resolveByToken($token);
        if ($review === null) {
            return response()->view('public.transport.review-expired', [], 410);
        }

        if ($review->status !== 'invited' || $review->submitted_at !== null) {
            // Token użyty wcześniej — friendly page (200), nie 404.
            return response()->view('public.transport.review-already-submitted', [
                'transporter' => $this->resolveTenant($review->transporter_tenant_id),
            ]);
        }

        return view('public.transport.review-form', [
            'token' => $token,
            'review' => $review,
            'transporter' => $this->resolveTenant($review->transporter_tenant_id),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $review = $this->resolveByToken($token);
        if ($review === null) {
            return redirect()->route('public.transport.review.expired');
        }

        if ($review->status !== 'invited' || $review->submitted_at !== null) {
            return redirect()->route('public.transport.review.thanks');
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->forceFill([
            'rating' => (int) $data['rating'],
            'comment' => isset($data['comment']) ? mb_substr((string) $data['comment'], 0, 2000) : null,
            'status' => 'published',
            'submitted_at' => now(),
        ])->save();

        TransportReview::forgetAggregateCache($review->transporter_tenant_id);

        $this->notifyTransporterOwner($review);

        return redirect()->route('public.transport.review.thanks');
    }

    public function thanks(): View
    {
        return view('public.transport.review-thanks');
    }

    public function expired(): Response
    {
        return response()->view('public.transport.review-expired', [], 410);
    }

    /**
     * Wymaga: format tokenu poprawny + hash matchuje + token nie wygasł.
     */
    private function resolveByToken(string $token): ?TransportReview
    {
        if (! preg_match('/^[A-Za-z0-9]{40,80}$/', $token)) {
            return null;
        }

        $hash = hash('sha256', $token);

        $review = TransportReview::query()
            ->where('invite_token_hash', $hash)
            ->first();

        if ($review === null) {
            return null;
        }

        // Token TTL: jeśli minął expires_at i jeszcze nie submitted —
        // tokeen martwy. Submitted = pokazujemy friendly page niezależnie
        // od TTL (klient może chcieć sprawdzić własną opinię z linka).
        if ($review->submitted_at === null && $review->invite_expires_at?->isPast()) {
            return null;
        }

        return $review;
    }

    private function resolveTenant(string $tenantId): ?Tenant
    {
        return Tenant::query()->find($tenantId);
    }

    private function notifyTransporterOwner(TransportReview $review): void
    {
        $email = TenantMembership::query()
            ->join('users', 'tenant_memberships.user_id', '=', 'users.id')
            ->where('tenant_memberships.tenant_id', $review->transporter_tenant_id)
            ->where('tenant_memberships.role', 'owner')
            ->whereNull('tenant_memberships.revoked_at')
            ->value('users.email');

        if (! $email) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)
                ->notify(new TransportReviewSubmittedNotification($review));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
