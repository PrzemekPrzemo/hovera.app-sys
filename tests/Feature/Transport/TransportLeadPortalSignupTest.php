<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Central\TransportLead;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in account creation z portalu leada — klient po wypełnieniu publicznego
 * /transport/zapytanie dostaje email z linkiem do portalu, gdzie może opt-in
 * zarejestrować konto żeby widzieć historię wszystkich swoich zapytań.
 *
 * Pokrycie: signup form renders, submit tworzy User + backfill'uje
 * originator_user_id, auto-login, duplikat email → exists page,
 * /transport/moje-zapytania pokazuje wszystkie leady authenticated klienta.
 */
class TransportLeadPortalSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_form_renders_with_email_prefilled(): void
    {
        $lead = $this->makeLead('anna@example.com');

        $response = $this->get(route('public.transport.lead_portal.signup', ['slug' => $lead->access_slug]));

        $response->assertOk();
        $response->assertSee('anna@example.com', false);
        $response->assertSee('password', false);
        $response->assertSee('terms', false);
    }

    public function test_signup_submit_creates_user_and_backfills_originator_user_id(): void
    {
        // Klient ma 2 leady (jeden niedawny, jeden starszy), oba na ten sam email.
        $lead1 = $this->makeLead('client@example.com');
        $lead2 = $this->makeLead('client@example.com');

        $response = $this->post(route('public.transport.lead_portal.signup.submit', ['slug' => $lead1->access_slug]), [
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'terms' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('public.transport.my_inquiries'));

        $user = User::query()->where('email', 'client@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SuperSecret123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_master_admin);

        // Oba leady linkowane do nowego usera (klient odzyskuje pełną historię).
        $lead1->refresh();
        $lead2->refresh();
        $this->assertSame($user->id, $lead1->originator_user_id);
        $this->assertSame($user->id, $lead2->originator_user_id);
    }

    public function test_signup_submit_logs_user_in_automatically(): void
    {
        $lead = $this->makeLead('newbie@example.com');

        $this->post(route('public.transport.lead_portal.signup.submit', ['slug' => $lead->access_slug]), [
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'terms' => '1',
            'website' => '',
        ]);

        $this->assertAuthenticated();
        $this->assertSame('newbie@example.com', auth()->user()->email);
    }

    public function test_signup_fails_when_terms_not_accepted(): void
    {
        $lead = $this->makeLead('terms@example.com');

        $response = $this->post(route('public.transport.lead_portal.signup.submit', ['slug' => $lead->access_slug]), [
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'website' => '',
        ]);

        $response->assertSessionHasErrors(['terms']);
        $this->assertSame(0, User::where('email', 'terms@example.com')->count());
    }

    public function test_signup_fails_when_passwords_dont_match(): void
    {
        $lead = $this->makeLead('mismatch@example.com');

        $response = $this->post(route('public.transport.lead_portal.signup.submit', ['slug' => $lead->access_slug]), [
            'password' => 'SuperSecret123',
            'password_confirmation' => 'Different456',
            'terms' => '1',
            'website' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertSame(0, User::where('email', 'mismatch@example.com')->count());
    }

    public function test_signup_blocks_honeypot_filled(): void
    {
        $lead = $this->makeLead('bot@example.com');

        $response = $this->post(route('public.transport.lead_portal.signup.submit', ['slug' => $lead->access_slug]), [
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'terms' => '1',
            'website' => 'http://spam.bot',
        ]);

        $response->assertSessionHasErrors(['website']);
        $this->assertSame(0, User::where('email', 'bot@example.com')->count());
    }

    public function test_signup_form_redirects_to_exists_page_when_account_already_present(): void
    {
        User::create([
            'email' => 'taken@example.com',
            'name' => 'Existing',
            'password' => Hash::make('secret'),
        ]);

        $lead = $this->makeLead('taken@example.com');

        $response = $this->get(route('public.transport.lead_portal.signup', ['slug' => $lead->access_slug]));

        $response->assertOk();
        // Exists page nie zawiera form'a hasła (różny szkic).
        $response->assertSee('taken@example.com', false);
        $response->assertDontSee('name="password"', false);
    }

    public function test_my_inquiries_requires_authentication(): void
    {
        // Laravel auth middleware → /login → /app/login (defined w routes/web.php).
        $this->get(route('public.transport.my_inquiries'))
            ->assertRedirect('/login');
    }

    public function test_my_inquiries_lists_user_leads_by_user_id_and_email(): void
    {
        $user = User::create([
            'email' => 'history@example.com',
            'name' => 'Test',
            'password' => Hash::make('secret'),
        ]);

        // Lead 1: powiązany przez originator_user_id (po signupie wstecznie).
        $lead1 = $this->makeLead('history@example.com', ['originator_user_id' => $user->id, 'pickup_address' => 'Warszawa, Punkt A']);
        // Lead 2: tylko email match (lead utworzony PRZED założeniem konta, gdyby
        // backfill nie zaszedł — drugi WHERE w query gwarantuje pełną historię).
        $lead2 = $this->makeLead('history@example.com', ['pickup_address' => 'Kraków, Punkt B']);
        // Lead 3: kogoś innego — nie powinien się pojawić.
        $this->makeLead('other@example.com', ['pickup_address' => 'Tajna Trasa Innego']);

        $response = $this->actingAs($user)->get(route('public.transport.my_inquiries'));

        $response->assertOk();
        $response->assertSee('Warszawa, Punkt A');
        $response->assertSee('Kraków, Punkt B');
        $response->assertDontSee('Tajna Trasa Innego');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeLead(string $email, array $overrides = []): TransportLead
    {
        return TransportLead::create(array_merge([
            'id' => (string) Str::ulid(),
            'access_slug' => (string) Str::uuid(),
            'mode' => 'broadcast',
            'originator_name' => 'Klient '.uniqid(),
            'originator_email' => $email,
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 52.0,
            'pickup_lng' => 21.0,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 50.0,
            'dropoff_lng' => 19.9,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }
}
