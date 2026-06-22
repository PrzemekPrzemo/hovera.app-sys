<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ExternalSpecialistResource\Pages;
use App\Models\Central\ExternalSpecialist;
use App\Services\MasterAuditLogger;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Master-admin: management external specialists (PR O5 Channel B).
 *
 * Lista vet/farrier/etc. z central DB. Master-admin manualnie weryfikuje
 * (sprawdza PWZ / licencję) — bez weryfikacji thread'y w UI pokazują
 * "niezweryfikowany" badge.
 *
 * Per captured decisions §3 (hybrid invite):
 *   - Stable/owner zaprasza dowolny email → ExternalSpecialist created
 *   - Magic-link setup → password_hash set, email_verified_at set
 *   - Master-admin verify here → verified_at + verified_by_user_id set
 *     → UI badge "verified" pojawia się
 */
class ExternalSpecialistResource extends Resource
{
    protected static ?string $model = ExternalSpecialist::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('admin/external_specialist.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.users');
    }

    public static function getModelLabel(): string
    {
        return __('admin/external_specialist.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/external_specialist.model.plural');
    }

    public static function form(Form $form): Form
    {
        // Form read-only — master-admin nie tworzy specialist'a (to robi
        // tenant przez invite). Może tylko zmienić display_name / specialty
        // / phone jeśli dane są błędne, albo zatwierdzić weryfikację przez
        // action z listy.
        return $form->schema([
            Forms\Components\Section::make(__('admin/external_specialist.form.section.identity'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label(__('admin/external_specialist.form.label.email'))
                        ->disabled(),
                    Forms\Components\TextInput::make('display_name')
                        ->label(__('admin/external_specialist.form.label.display_name'))
                        ->required(),
                    Forms\Components\TextInput::make('specialty')
                        ->label(__('admin/external_specialist.form.label.specialty')),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('admin/external_specialist.form.label.phone')),
                ]),

            Forms\Components\Section::make(__('admin/external_specialist.form.section.status'))
                ->columns(2)
                ->schema([
                    Forms\Components\Placeholder::make('setup_status')
                        ->label(__('admin/external_specialist.form.label.setup_status'))
                        ->content(fn (?ExternalSpecialist $record) => $record?->has_completed_setup
                            ? __('admin/external_specialist.status.setup_complete')
                            : __('admin/external_specialist.status.setup_pending')),
                    Forms\Components\Placeholder::make('verified_at')
                        ->label(__('admin/external_specialist.form.label.verified_at'))
                        ->content(fn (?ExternalSpecialist $record) => $record?->verified_at?->translatedFormat('d.m.Y H:i')
                            ?? __('admin/external_specialist.status.not_verified')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(__('admin/external_specialist.table.email'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('admin/external_specialist.table.display_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('specialty')
                    ->label(__('admin/external_specialist.table.specialty'))
                    ->badge(),
                Tables\Columns\IconColumn::make('has_completed_setup')
                    ->label(__('admin/external_specialist.table.setup'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('verified_at')
                    ->label(__('admin/external_specialist.table.verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (ExternalSpecialist $r) => $r->verified_at !== null),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin/external_specialist.table.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('not_verified')
                    ->label(__('admin/external_specialist.filter.not_verified'))
                    ->query(fn (Builder $q) => $q->whereNull('verified_at'))
                    ->default(),
                Tables\Filters\Filter::make('setup_pending')
                    ->label(__('admin/external_specialist.filter.setup_pending'))
                    ->query(fn (Builder $q) => $q->whereNull('password_hash')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                self::verifyAction(),
                self::unverifyAction(),
            ]);
    }

    /**
     * "Zweryfikuj specjalistę" — master-admin po sprawdzeniu PWZ /
     * licencji oznacza konto jako zweryfikowane. Po tym UI thread'ów
     * pokazuje badge "verified" zamiast "unverified".
     */
    private static function verifyAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('verify')
            ->label(__('admin/external_specialist.action.verify.label'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (ExternalSpecialist $r) => $r->verified_at === null)
            ->requiresConfirmation()
            ->modalHeading(__('admin/external_specialist.action.verify.modal_heading'))
            ->modalDescription(__('admin/external_specialist.action.verify.modal_description'))
            ->action(function (ExternalSpecialist $record): void {
                $user = Auth::user();

                $record->forceFill([
                    'verified_at' => now(),
                    'verified_by_user_id' => $user?->id,
                ])->save();

                app(MasterAuditLogger::class)->record(
                    'external_specialist.verified',
                    'ExternalSpecialist',
                    (string) $record->id,
                    null,
                    [
                        'email' => $record->email,
                        'specialty' => $record->specialty,
                        'verified_by' => $user?->email,
                    ],
                );

                Notification::make()
                    ->success()
                    ->title(__('admin/external_specialist.action.verify.notify_title'))
                    ->body(__('admin/external_specialist.action.verify.notify_body', ['email' => $record->email]))
                    ->send();
            });
    }

    /**
     * "Wycofaj weryfikację" — gdy wyjdzie na jaw że licencja wygasła /
     * fraud / błąd. Powoduje powrót do "unverified" badge w UI.
     */
    private static function unverifyAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('unverify')
            ->label(__('admin/external_specialist.action.unverify.label'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (ExternalSpecialist $r) => $r->verified_at !== null)
            ->requiresConfirmation()
            ->modalHeading(__('admin/external_specialist.action.unverify.modal_heading'))
            ->modalDescription(__('admin/external_specialist.action.unverify.modal_description'))
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label(__('admin/external_specialist.action.unverify.reason'))
                    ->required()
                    ->minLength(10)
                    ->rows(3),
            ])
            ->action(function (ExternalSpecialist $record, array $data): void {
                $user = Auth::user();
                $previousVerifiedAt = $record->verified_at;

                $record->forceFill([
                    'verified_at' => null,
                    'verified_by_user_id' => null,
                ])->save();

                app(MasterAuditLogger::class)->record(
                    'external_specialist.unverified',
                    'ExternalSpecialist',
                    (string) $record->id,
                    null,
                    [
                        'email' => $record->email,
                        'reason' => $data['reason'],
                        'previous_verified_at' => $previousVerifiedAt?->toIso8601String(),
                        'unverified_by' => $user?->email,
                    ],
                );

                Notification::make()
                    ->success()
                    ->title(__('admin/external_specialist.action.unverify.notify_title'))
                    ->body(__('admin/external_specialist.action.unverify.notify_body', ['email' => $record->email]))
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalSpecialists::route('/'),
            'edit' => Pages\EditExternalSpecialist::route('/{record}/edit'),
        ];
    }
}
