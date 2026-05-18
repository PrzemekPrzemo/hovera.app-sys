<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Models\Central\TransportLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Publiczny formularz zapytania o transport koni. Bez auth — każdy może
 * wypełnić, my tworzymy `transport_lead` w status=open. LeadDispatcher
 * (faza 5+6 krok 4) podejmuje to dispatch'em do transporterów.
 *
 * Routes:
 *   GET  /transport/zapytanie          → formularz
 *   POST /transport/zapytanie          → submit + redirect na dziekujemy
 *   GET  /transport/zapytanie/dziekujemy/{lead}  → potwierdzenie
 *
 * Throttle POST 5/h z IP (anti-spam, mocniej niż signup bo lead = mail
 * leci do transporterów).
 */
class TransportInquiryController extends Controller
{
    public function show(Request $request): View
    {
        return view('public.transport.inquiry', [
            'old' => [
                'customer_name' => (string) old('customer_name'),
                'customer_email' => (string) old('customer_email'),
                'customer_phone' => (string) old('customer_phone'),
                'pickup_address' => (string) old('pickup_address'),
                'dropoff_address' => (string) old('dropoff_address'),
                'preferred_date' => (string) old('preferred_date', now()->addDays(7)->toDateString()),
                'preferred_time' => (string) old('preferred_time'),
                'horse_count' => (int) old('horse_count', 1),
                'notes' => (string) old('notes'),
            ],
        ]);
    }

    public function submit(Request $request, MapboxGeocoder $geocoder): RedirectResponse
    {
        $data = $this->validate($request);

        try {
            $from = $geocoder->geocode($data['pickup_address']);
            $to = $geocoder->geocode($data['dropoff_address']);
        } catch (GeocodingException $e) {
            return back()
                ->withErrors(['address' => __('public/transport_inquiry.error.geocoding', ['msg' => $e->getMessage()])])
                ->withInput();
        }

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'originator_name' => $data['customer_name'],
            'originator_email' => $data['customer_email'],
            'originator_phone' => $data['customer_phone'] ?? null,
            'pickup_address' => $from->displayName,
            'pickup_lat' => $from->coords->lat,
            'pickup_lng' => $from->coords->lng,
            'pickup_voivodeship' => $from->voivodeship ?? '',
            'dropoff_address' => $to->displayName,
            'dropoff_lat' => $to->coords->lat,
            'dropoff_lng' => $to->coords->lng,
            'dropoff_voivodeship' => $to->voivodeship ?? '',
            'preferred_date' => $data['preferred_date'],
            'preferred_time' => $data['preferred_time'] ?? null,
            'flexible_date' => (bool) ($data['flexible_date'] ?? false),
            'horse_count' => $data['horse_count'],
            'notes' => $data['notes'] ?? null,
            'status' => 'open',
            'expires_at' => Carbon::now()->addDays((int) config('transport.leads.expires_after_days', 14)),
        ]);

        // Dispatch — sync, bo zwykle 0-30 transporterów per voivodeship; gdy
        // marketplace urośnie >50 + adjacency, zqueueujemy to przez Job.
        // Notyfikacje email lecą wewnątrz, fail któregokolwiek nie psuje
        // pozostałych (per-transporter try/catch).
        app(LeadDispatcher::class)->dispatch($lead);

        return redirect()->route('public.transport.inquiry.thanks', ['lead' => $lead->id]);
    }

    public function thanks(string $leadId): View
    {
        $lead = TransportLead::query()->where('id', $leadId)->firstOrFail();

        return view('public.transport.inquiry-thanks', ['lead' => $lead]);
    }

    /**
     * @return array{
     *   customer_name:string, customer_email:string, customer_phone:?string,
     *   pickup_address:string, dropoff_address:string,
     *   preferred_date:string, preferred_time:?string, flexible_date?:bool,
     *   horse_count:int, notes:?string
     * }
     */
    private function validate(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_email' => ['required', 'email:rfc,strict', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'pickup_address' => ['required', 'string', 'min:3', 'max:255'],
            'dropoff_address' => ['required', 'string', 'min:3', 'max:255'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'flexible_date' => ['nullable', 'boolean'],
            'horse_count' => ['required', 'integer', 'min:1', 'max:15'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('public/transport_inquiry.error.terms'),
        ]);
    }
}
