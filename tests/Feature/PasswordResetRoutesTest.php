<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Smoke test that ->passwordReset() is wired on both Filament panels.
 * We don't exercise the full reset flow here (Laravel/Filament's own
 * tests cover that) — just that the routes exist and the request
 * form renders without auth.
 */
class PasswordResetRoutesTest extends TestCase
{
    public function test_admin_password_reset_request_route_is_registered(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.auth.password-reset.request'));
    }

    public function test_app_password_reset_request_route_is_registered(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('filament.app.auth.password-reset.request'));
    }

    public function test_admin_password_reset_request_renders_without_auth(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_app_password_reset_request_renders_without_auth(): void
    {
        $this->get('/app/password-reset/request')->assertOk();
    }
}
