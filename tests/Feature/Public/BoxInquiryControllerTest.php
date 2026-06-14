<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\BoxInquiry;
use App\Notifications\Stable\BoxInquiryReceivedNotification;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Public box-inquiry flow (no auth):
 *   GET  /s/{slug}/box-inquiry          → form
 *   POST /s/{slug}/box-inquiry          → save + notify owner
 *   GET  /s/{slug}/box-inquiry/thanks   → confirmation
 *
 * Plus honeypot anti-spam + validation.
 */
class BoxInquiryControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_inquiry_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->bootTenant();

        Notification::fake();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_form_renders_for_existing_tenant(): void
    {
        $this->get('/s/'.$this->tenant->slug.'/box-inquiry')
            ->assertOk()
            ->assertSee($this->tenant->name)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false);
    }

    public function test_form_404s_for_unknown_slug(): void
    {
        $this->get('/s/nonexistent-stable/box-inquiry')->assertNotFound();
    }

    public function test_valid_submission_creates_inquiry_and_notifies_owner(): void
    {
        $this->seedOwnerWithEmail('owner@stable.test');

        $this->post('/s/'.$this->tenant->slug.'/box-inquiry', [
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.test',
            'phone' => '+48600100200',
            'horse_count' => 2,
            'preferred_from' => now()->addMonth()->toDateString(),
            'message' => 'Mam dwa konie sportowe, szukam boksu z padokiem.',
            'source' => 'embed',
        ])
            ->assertRedirect('/s/'.$this->tenant->slug.'/box-inquiry/thanks');

        $inquiry = BoxInquiry::query()->first();
        $this->assertNotNull($inquiry);
        $this->assertSame('Anna Kowalska', $inquiry->name);
        $this->assertSame('anna@example.test', $inquiry->email);
        $this->assertSame(2, $inquiry->horse_count);
        $this->assertSame(BoxInquiry::STATUS_NEW, $inquiry->status);
        $this->assertSame('embed', $inquiry->source);

        Notification::assertSentOnDemand(BoxInquiryReceivedNotification::class);
    }

    public function test_honeypot_marks_inquiry_as_spam_and_does_not_notify(): void
    {
        $this->seedOwnerWithEmail('owner@stable.test');

        $this->post('/s/'.$this->tenant->slug.'/box-inquiry', [
            'name' => 'Bot Name',
            'email' => 'bot@example.test',
            'horse_count' => 1,
            'company' => 'BotCorp Industries', // honeypot — only bots fill
        ])->assertRedirect();

        $inquiry = BoxInquiry::query()->first();
        $this->assertSame(BoxInquiry::STATUS_SPAM, $inquiry->status);
        Notification::assertNothingSent();
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $this->from('/s/'.$this->tenant->slug.'/box-inquiry')
            ->post('/s/'.$this->tenant->slug.'/box-inquiry', [
                'name' => '',
                'email' => 'not-an-email',
                'horse_count' => 0,
            ])
            ->assertRedirect('/s/'.$this->tenant->slug.'/box-inquiry')
            ->assertSessionHasErrors(['name', 'email', 'horse_count']);

        $this->assertSame(0, BoxInquiry::query()->count());
    }

    public function test_validation_rejects_preferred_from_in_the_past(): void
    {
        $this->post('/s/'.$this->tenant->slug.'/box-inquiry', [
            'name' => 'X',
            'email' => 'x@y.test',
            'horse_count' => 1,
            'preferred_from' => now()->subWeek()->toDateString(),
        ])->assertSessionHasErrors('preferred_from');
    }

    public function test_thanks_page_renders(): void
    {
        $this->get('/s/'.$this->tenant->slug.'/box-inquiry/thanks')
            ->assertOk()
            ->assertSee($this->tenant->name);
    }

    public function test_inquiry_captures_ip_and_user_agent(): void
    {
        $this->post('/s/'.$this->tenant->slug.'/box-inquiry', [
            'name' => 'Y',
            'email' => 'y@z.test',
            'horse_count' => 1,
        ], ['User-Agent' => 'TestBrowser/1.0']);

        $inquiry = BoxInquiry::query()->first();
        $this->assertNotNull($inquiry->ip_address);
        $this->assertStringContainsString('TestBrowser', (string) $inquiry->user_agent);
    }

    private function bootTenant(): void
    {
        $this->tenant = Tenant::create([
            'slug' => 'stajnia-test',
            'name' => 'Stajnia Test',
            'type' => TenantType::Stable,
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);
    }

    private function seedOwnerWithEmail(string $email): void
    {
        $user = User::create([
            'email' => $email,
            'name' => 'Owner',
            'password' => bcrypt('x'),
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('box_inquiries', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('name', 160);
            $t->string('email', 255);
            $t->string('phone', 40)->nullable();
            $t->unsignedSmallInteger('horse_count')->default(1);
            $t->date('preferred_from')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 16)->default('new');
            $t->timestamp('responded_at')->nullable();
            $t->string('responded_by_user_id', 26)->nullable();
            $t->text('response_notes')->nullable();
            $t->string('source', 32)->default('public_site');
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
