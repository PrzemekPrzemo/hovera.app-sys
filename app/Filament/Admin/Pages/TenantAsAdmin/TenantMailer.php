<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\TenantAsAdmin;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMessage;
use App\Notifications\CustomTenantMessage;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Master-admin mailer to a single tenant's owners + admins. Sales/ops
 * uses this for promo announcements, payment reminders, support
 * follow-ups — anything that doesn't justify a system-wide email.
 *
 * Every send writes a row into central.tenant_messages so we can show
 * the operator a "you sent X on Y" history per tenant later.
 */
class TenantMailer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.tenant-as-admin.mailer';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'tenants/{tenantId}/mailer';

    public string $tenantId = '';

    /** @var array<string,mixed> */
    public array $data = [];

    /** @var list<array<string,mixed>> */
    public array $history = [];

    public function getTitle(): string|Htmlable
    {
        return __('admin/back-office.mailer.title', ['name' => $this->tenant()->name]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/admin/tenants') => __('navigation.tenants'),
            url('/admin/tenants/'.$this->tenant()->id.'/edit') => $this->tenant()->name,
            __('admin/back-office.mailer.breadcrumb') => '',
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->is_master_admin;
    }

    public function mount(string $tenantId): void
    {
        abort_unless(self::canAccess(), 403);
        $this->tenantId = $tenantId;
        $this->form->fill([
            'template' => 'custom',
            'subject' => '',
            'body' => '',
        ]);
        $this->loadHistory();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('template')
                    ->label(__('admin/back-office.mailer.label.template'))
                    ->options([
                        'custom' => __('admin/back-office.mailer.template.custom'),
                        'promo' => __('admin/back-office.mailer.template.promo'),
                        'reminder' => __('admin/back-office.mailer.template.reminder'),
                        'support' => __('admin/back-office.mailer.template.support'),
                    ])
                    ->default('custom')
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                        // Only auto-fill when the operator hasn't typed
                        // anything yet — protects an in-progress draft
                        // from being clobbered when they accidentally
                        // change the template select.
                        if (! empty($get('subject')) || ! empty($get('body'))) {
                            return;
                        }
                        [$subj, $body] = self::canonicalTemplate((string) $state);
                        $set('subject', $subj);
                        $set('body', $body);
                    }),
                Forms\Components\TextInput::make('subject')
                    ->label(__('admin/back-office.mailer.label.subject'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('body')
                    ->label(__('admin/back-office.mailer.label.body'))
                    ->required()
                    ->rows(10)
                    ->maxLength(10000)
                    ->helperText(__('admin/back-office.mailer.label.body_helper')),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        abort_unless(self::canAccess(), 403);

        $data = $this->form->getState();
        $tenant = $this->tenant();

        $owners = $tenant->memberships()
            ->whereNull('revoked_at')
            ->whereIn('role', ['owner', 'admin'])
            ->with('user')
            ->get()
            ->map(fn ($m) => $m->user)
            ->filter(fn ($u) => $u !== null && filter_var($u->email, FILTER_VALIDATE_EMAIL))
            ->unique('id')
            ->values();

        if ($owners->isEmpty()) {
            Notification::make()->danger()
                ->title(__('admin/back-office.mailer.no_recipients'))
                ->send();

            return;
        }

        $notification = new CustomTenantMessage(
            subject: (string) $data['subject'],
            body: (string) $data['body'],
            tenantName: $tenant->name,
        );

        try {
            NotificationFacade::send($owners, $notification);
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title(__('admin/back-office.mailer.failed'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        $message = TenantMessage::create([
            'tenant_id' => $tenant->id,
            'sent_by_user_id' => Auth::id(),
            'template' => (string) ($data['template'] ?? 'custom'),
            'subject' => (string) $data['subject'],
            'body' => (string) $data['body'],
            'recipients_count' => $owners->count(),
            'recipients' => $owners->pluck('email')->values()->all(),
            'sent_at' => now(),
        ]);

        app(MasterAuditLogger::class)->record(
            'tenant.mailer.send',
            'Tenant',
            $tenant->id,
            $tenant->id,
            [
                'message_id' => $message->id,
                'recipients_count' => $owners->count(),
                'template' => $data['template'] ?? 'custom',
                'subject' => $data['subject'],
            ],
        );

        Notification::make()->success()
            ->title(__('admin/back-office.mailer.sent_title'))
            ->body(__('admin/back-office.mailer.sent_body', ['count' => $owners->count()]))
            ->send();

        $this->form->fill(['template' => 'custom', 'subject' => '', 'body' => '']);
        $this->loadHistory();
    }

    public function loadHistory(): void
    {
        $this->history = TenantMessage::query()
            ->where('tenant_id', $this->tenantId)
            ->orderByDesc('sent_at')
            ->limit(20)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'subject' => $m->subject,
                'template' => $m->template,
                'recipients_count' => $m->recipients_count,
                'sent_at' => $m->sent_at?->translatedFormat('d.m.Y H:i'),
            ])
            ->all();
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function canonicalTemplate(string $template): array
    {
        return match ($template) {
            'promo' => [
                __('admin/back-office.mailer.canon.promo.subject'),
                __('admin/back-office.mailer.canon.promo.body'),
            ],
            'reminder' => [
                __('admin/back-office.mailer.canon.reminder.subject'),
                __('admin/back-office.mailer.canon.reminder.body'),
            ],
            'support' => [
                __('admin/back-office.mailer.canon.support.subject'),
                __('admin/back-office.mailer.canon.support.body'),
            ],
            default => ['', ''],
        };
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->withTrashed()->findOrFail($this->tenantId);
    }
}
