<?php

declare(strict_types=1);

namespace App\Filament\Transport\Pages;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Tenancy\TenantRoleGate;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Embed snippet generator — pozwala transporterowi wygenerować gotowy
 * HTML+JS do wklejenia na własną stronę. Form posta do
 * `/api/transport/inquiry`, gated przez `embed_allowed_origins` +
 * `X-Hovera-Embed-Token`. Patrz docs/TRANSPORT.md §16.
 *
 * Konfig żyje na central `tenants` (nie per-tenant DB), bo CORS middleware
 * i tak musi rezolwować tenant po slug'u przed switching connection.
 */
class EmbedSnippet extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    public static function getNavigationLabel(): string
    {
        return __('transport/embed_snippet.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('transport/embed_snippet.title');
    }

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.transport.pages.embed-snippet';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $origins = is_array($tenant->embed_allowed_origins) ? $tenant->embed_allowed_origins : [];

        $this->form->fill([
            'origins' => array_map(static fn (string $o): array => ['url' => $o], $origins),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('transport/embed_snippet.section.origins'))
                    ->description(__('transport/embed_snippet.section.origins_description'))
                    ->schema([
                        Forms\Components\Repeater::make('origins')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('url')
                                    ->label(__('transport/embed_snippet.form.origin_url'))
                                    ->placeholder('https://example.com')
                                    ->url()
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->addActionLabel(__('transport/embed_snippet.form.add_origin'))
                            ->reorderable(false)
                            ->collapsible(false)
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make(__('transport/embed_snippet.section.token'))
                    ->description(__('transport/embed_snippet.section.token_description'))
                    ->schema([
                        Forms\Components\Placeholder::make('token_status')
                            ->label(__('transport/embed_snippet.form.token_status_label'))
                            ->content(fn () => $this->tokenStatusLabel()),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('transport/embed_snippet.action.save'))
                ->submit('save'),
            Actions\Action::make('regenerateToken')
                ->label(__('transport/embed_snippet.action.regenerate_token'))
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription(__('transport/embed_snippet.action.regenerate_token_confirm'))
                ->action(fn () => $this->regenerateToken()),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        $form = $this->form->getState();
        $origins = collect((array) ($form['origins'] ?? []))
            ->pluck('url')
            ->filter(static fn ($v) => is_string($v) && trim($v) !== '')
            ->map(static fn (string $v): string => rtrim(trim($v), '/'))
            ->unique()
            ->values()
            ->all();

        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->embed_allowed_origins = $origins;
        $tenant->save();

        app(TenantAuditLogger::class)->record(
            'transport.embed_origins_updated',
            'Tenant',
            (string) $tenant->id,
            ['count' => count($origins)],
        );

        Notification::make()
            ->success()
            ->title(__('transport/embed_snippet.notify.saved'))
            ->body(__('transport/embed_snippet.notify.saved_body', ['count' => count($origins)]))
            ->send();
    }

    public function regenerateToken(): void
    {
        abort_unless(self::canAccess(), 403);

        $tenant = app(TenantManager::class)->tenantOrFail();
        $tenant->regenerateEmbedApiToken();

        app(TenantAuditLogger::class)->record(
            'transport.embed_token_regenerated',
            'Tenant',
            (string) $tenant->id,
        );

        Notification::make()
            ->warning()
            ->title(__('transport/embed_snippet.notify.token_regenerated'))
            ->body(__('transport/embed_snippet.notify.token_regenerated_body'))
            ->persistent()
            ->send();
    }

    /**
     * Snippet HTML+JS gotowy do skopiowania. Renderowany z template'a z
     * podstawionym tenant slug + apiToken + brand color.
     */
    public function getSnippetCode(): string
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $token = (string) ($tenant->embed_api_token ?? '');

        if ($token === '') {
            return __('transport/embed_snippet.snippet.requires_token');
        }

        return view('embed-snippet.template', [
            'tenantSlug' => (string) $tenant->slug,
            'apiToken' => $token,
            'apiUrl' => url('/api/transport/inquiry'),
            'brandColor' => (string) (($tenant->branding['primary_color'] ?? '') ?: '#A8956B'),
            'companyName' => (string) $tenant->name,
        ])->render();
    }

    private function tokenStatusLabel(): string
    {
        $tenant = app(TenantManager::class)->tenantOrFail();
        $token = (string) ($tenant->embed_api_token ?? '');
        if ($token === '') {
            return __('transport/embed_snippet.form.token_missing');
        }

        $preview = substr($token, 0, 6).'...'.substr($token, -4);

        return __('transport/embed_snippet.form.token_present', ['preview' => $preview]);
    }
}
