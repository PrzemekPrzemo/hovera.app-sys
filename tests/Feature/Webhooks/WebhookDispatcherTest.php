<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\Central\Tenant;
use App\Models\Central\WebhookDelivery;
use App\Models\Central\WebhookSubscription;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_queues_job_per_matching_active_subscription(): void
    {
        Bus::fake();

        $tenant = $this->makeTenant();

        $matchA = WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://hookA.example.com/in',
            'events' => ['invoice.paid'],
            'secret' => 'whsec_a',
            'is_active' => true,
        ]);
        WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://wrong-event.example.com/in',
            'events' => ['booking.created'],
            'secret' => 'whsec_b',
            'is_active' => true,
        ]);
        WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://inactive.example.com/in',
            'events' => ['invoice.paid'],
            'secret' => 'whsec_c',
            'is_active' => false,
        ]);

        $count = app(WebhookDispatcher::class)->dispatch(
            tenantId: $tenant->id,
            event: 'invoice.paid',
            payload: ['invoice_id' => 'INV-1', 'amount' => 199.0],
        );

        $this->assertSame(1, $count);
        Bus::assertDispatched(DeliverWebhookJob::class, function (DeliverWebhookJob $job) use ($matchA) {
            return $job->subscriptionId === $matchA->id
                && $job->event === 'invoice.paid'
                && ($job->body['data']['invoice_id'] ?? null) === 'INV-1';
        });
    }

    public function test_deliver_job_signs_body_with_hmac_and_records_delivery(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://endpoint.example.com/in' => Http::response('ok', 200),
        ]);

        $tenant = $this->makeTenant();
        $sub = WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://endpoint.example.com/in',
            'events' => ['invoice.paid'],
            'secret' => 'whsec_test_key',
            'is_active' => true,
        ]);

        $body = [
            'event' => 'invoice.paid',
            'tenant_id' => $tenant->id,
            'occurred_at' => '2026-05-10T12:00:00+00:00',
            'data' => ['invoice_id' => 'INV-9'],
        ];

        (new DeliverWebhookJob($sub->id, 'invoice.paid', $body, 1))->handle();

        $expectedSig = 'sha256='.hash_hmac(
            'sha256',
            (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'whsec_test_key',
        );

        Http::assertSent(function ($request) use ($expectedSig) {
            return $request->url() === 'https://endpoint.example.com/in'
                && $request->method() === 'POST'
                && $request->header('X-Hovera-Signature')[0] === $expectedSig
                && $request->header('X-Hovera-Event')[0] === 'invoice.paid';
        });

        $delivery = WebhookDelivery::query()->first();
        $this->assertNotNull($delivery);
        $this->assertSame(200, $delivery->status_code);
        $this->assertSame('invoice.paid', $delivery->event);
        $this->assertSame(1, $delivery->attempt_number);

        $sub->refresh();
        $this->assertSame('success', $sub->last_delivery_status);
        $this->assertNotNull($sub->last_delivery_at);
    }

    public function test_4xx_response_marks_delivery_client_error_and_does_not_retry(): void
    {
        Http::fake([
            'https://endpoint.example.com/in' => Http::response('bad', 400),
        ]);

        $tenant = $this->makeTenant();
        $sub = WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://endpoint.example.com/in',
            'events' => ['invoice.paid'],
            'secret' => 'whsec_x',
            'is_active' => true,
        ]);

        (new DeliverWebhookJob($sub->id, 'invoice.paid', ['data' => []], 1))->handle();

        $sub->refresh();
        $this->assertSame('client_error', $sub->last_delivery_status);
        $this->assertSame(400, WebhookDelivery::query()->first()->status_code);
    }

    public function test_generated_secret_is_prefixed_for_secret_scanning(): void
    {
        $secret = WebhookSubscription::generateSecret();
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertGreaterThanOrEqual(48, strlen($secret) - strlen('whsec_'));
    }

    private function makeTenant(): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        return $tenant;
    }
}
