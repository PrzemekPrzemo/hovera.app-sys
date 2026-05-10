<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Master-admin's personal API tokens. Used for own monitoring scripts,
 * dashboards, alerts. Each token carries an abilities array (Sanctum scopes)
 * so we can later check `tokenCan('admin-all')` from API controllers.
 *
 * Plain-text token is shown EXACTLY ONCE at issue time (see
 * `$generatedToken` + the Livewire-bound modal in the Blade view) — losing
 * it means generating a new one. This is identical to GitHub PAT UX.
 */
class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.admin.pages.api-tokens';

    /** Plain-text token captured for one-time display after generation. */
    public ?string $generatedToken = null;

    /** Name shown alongside the generated token in the modal. */
    public ?string $generatedTokenName = null;

    public static function getNavigationLabel(): string
    {
        return __('admin/api-management.tokens.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin/api-management.tokens.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && (bool) ($user->is_master_admin ?? false);
    }

    /** @return list<string> */
    public static function abilityOptions(): array
    {
        return ['read-tenants', 'read-billing', 'read-system', 'admin-impersonate', 'admin-all'];
    }

    /** @return array<string,string> */
    public static function abilityLabels(): array
    {
        $out = [];
        foreach (self::abilityOptions() as $a) {
            $out[$a] = __('admin/api-management.tokens.abilities.'.$a);
        }

        return $out;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('admin/api-management.tokens.action.generate'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalSubmitActionLabel(__('admin/api-management.tokens.action.generate_submit'))
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin/api-management.tokens.form.name'))
                        ->required()
                        ->maxLength(120)
                        ->placeholder(__('admin/api-management.tokens.form.name_placeholder')),
                    Forms\Components\CheckboxList::make('abilities')
                        ->label(__('admin/api-management.tokens.form.abilities'))
                        ->options(self::abilityLabels())
                        ->columns(1)
                        ->required()
                        ->helperText(__('admin/api-management.tokens.form.abilities_help')),
                    Forms\Components\Select::make('expiry')
                        ->label(__('admin/api-management.tokens.form.expiry'))
                        ->options([
                            'none' => __('admin/api-management.tokens.form.expiry_none'),
                            '30d' => __('admin/api-management.tokens.form.expiry_30d'),
                            '90d' => __('admin/api-management.tokens.form.expiry_90d'),
                            '1y' => __('admin/api-management.tokens.form.expiry_1y'),
                        ])
                        ->default('90d')
                        ->required(),
                ])
                ->action(function (array $data, MasterAuditLogger $audit): void {
                    /** @var User $user */
                    $user = Auth::user();

                    $expires = match ($data['expiry']) {
                        '30d' => now()->addDays(30),
                        '90d' => now()->addDays(90),
                        '1y' => now()->addYear(),
                        default => null,
                    };

                    $abilities = (array) ($data['abilities'] ?? []);
                    $newToken = $user->createToken((string) $data['name'], $abilities, $expires);

                    // Stamp source IP/UA so they show up in the tenant-tokens
                    // overview alongside tokens issued by tenant users.
                    $newToken->accessToken->forceFill([
                        'issued_ip' => request()->ip(),
                        'issued_user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                    ])->save();

                    $this->generatedToken = $newToken->plainTextToken;
                    $this->generatedTokenName = (string) $data['name'];

                    $audit->record('api_token.created', 'PersonalAccessToken', (string) $newToken->accessToken->id, null, [
                        'name' => $data['name'],
                        'abilities' => $abilities,
                        'expires_at' => $expires?->toIso8601String(),
                    ]);

                    $this->dispatch('open-modal', id: 'token-revealed');
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->tokenQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin/api-management.tokens.col.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('abilities')
                    ->label(__('admin/api-management.tokens.col.abilities'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : (array) $state)
                    ->separator(','),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('admin/api-management.tokens.col.last_used_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/api-management.tokens.col.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('admin/api-management.tokens.col.expires_at'))
                    ->dateTime()
                    ->placeholder(__('admin/api-management.tokens.col.never'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label(__('admin/api-management.tokens.action.revoke'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin/api-management.tokens.action.revoke_confirm'))
                    ->action(function (PersonalAccessToken $record, MasterAuditLogger $audit): void {
                        $audit->record('api_token.revoked', 'PersonalAccessToken', (string) $record->id, null, [
                            'name' => $record->name,
                        ]);
                        $record->delete();
                        Notification::make()
                            ->success()
                            ->title(__('admin/api-management.tokens.action.revoke_success'))
                            ->send();
                    }),
            ]);
    }

    protected function tokenQuery(): Builder
    {
        $user = Auth::user();

        return PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->getKey());
    }
}
