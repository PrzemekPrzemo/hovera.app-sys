<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Tenants\CreateTenant;
use App\Models\Central\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Self-service signup → 30-dniowy trial. Stajnia podaje 4 pola, klika
 * "Załóż konto" → CreateTenant tworzy tenant + DB + invitation mail z
 * magic linkiem do ustawienia hasła ownera.
 *
 * Throttle 3 rejestracje na godzinę z jednego IP — anti-spam, nie
 * blokuje normalnego flow (kto się rejestruje 3× w godzinę?).
 */
class SignupController extends Controller
{
    public function show(Request $request): View
    {
        return view('public.signup.form', [
            'old' => [
                'name' => (string) old('name', $request->query('name', '')),
                'slug' => (string) old('slug', $request->query('slug', '')),
                'owner_name' => (string) old('owner_name', ''),
                'owner_email' => (string) old('owner_email', ''),
            ],
        ]);
    }

    public function submit(Request $request, CreateTenant $action): RedirectResponse
    {
        $data = $this->validate($request);

        try {
            $tenant = $action->execute([
                'slug' => $data['slug'],
                'name' => $data['name'],
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
            // Provisioner failed (DB user grant, network) — surface the error
            // gracefully instead of 500'ing the visitor.
            report($e);

            return back()
                ->withErrors(['signup' => __('public/signup.errors.provisioning_failed')])
                ->withInput();
        }

        return redirect()->route('signup.thanks', ['slug' => $tenant->slug]);
    }

    public function thanks(Request $request, string $slug): View
    {
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();

        return view('public.signup.thanks', [
            'tenant' => $tenant,
        ]);
    }

    /** @return array{name:string, slug:string, owner_name:string, owner_email:string} */
    private function validate(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:62',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,60}[a-z0-9])?$/',
                Rule::unique('central.tenants', 'slug'),
            ],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_email' => ['required', 'email:rfc,strict', 'max:255'],
            'terms' => ['accepted'],
        ], [
            'slug.regex' => __('public/signup.errors.slug_format'),
            'slug.unique' => __('public/signup.errors.slug_taken'),
            'terms.accepted' => __('public/signup.errors.terms'),
        ]);

        // Slug normalizujemy raz tu zanim pójdzie do CreateTenant.
        $data['slug'] = Str::lower($data['slug']);

        return $data;
    }
}
