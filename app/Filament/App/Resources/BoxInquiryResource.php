<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BoxInquiryResource\Pages;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\BoxInquiry;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoxInquiryResource extends Resource
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        // Owner/admin/manager kontaktują klientów + zamykają zapytania.
        // Instructor/viewer nie potrzebują tego widoku (i nie powinni mieć
        // dostępu do leadów sprzedażowych z zewnątrz).
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $model = BoxInquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?int $navigationSort = 22;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.stable');
    }

    public static function getNavigationLabel(): string
    {
        return __('app/box_inquiry.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('app/box_inquiry.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app/box_inquiry.model_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BoxInquiry::query()->where('status', BoxInquiry::STATUS_NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('app/box_inquiry.section.inquiry'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('app/box_inquiry.field.name'))
                        ->disabled(),
                    Forms\Components\TextInput::make('email')
                        ->label(__('app/box_inquiry.field.email'))
                        ->disabled(),
                    Forms\Components\TextInput::make('phone')
                        ->label(__('app/box_inquiry.field.phone'))
                        ->disabled(),
                    Forms\Components\TextInput::make('horse_count')
                        ->label(__('app/box_inquiry.field.horse_count'))
                        ->disabled(),
                    Forms\Components\DatePicker::make('preferred_from')
                        ->label(__('app/box_inquiry.field.preferred_from'))
                        ->disabled(),
                    Forms\Components\TextInput::make('source')
                        ->label(__('app/box_inquiry.field.source'))
                        ->disabled(),
                    Forms\Components\Textarea::make('message')
                        ->label(__('app/box_inquiry.field.message'))
                        ->disabled()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('app/box_inquiry.section.response'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label(__('app/box_inquiry.field.status'))
                        ->options(self::statusOptions())
                        ->required(),
                    Forms\Components\DateTimePicker::make('responded_at')
                        ->label(__('app/box_inquiry.field.responded_at')),
                    Forms\Components\Textarea::make('response_notes')
                        ->label(__('app/box_inquiry.field.response_notes'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app/box_inquiry.field.created_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('app/box_inquiry.field.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('app/box_inquiry.field.email'))
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('app/box_inquiry.field.phone'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('horse_count')
                    ->label(__('app/box_inquiry.field.horse_count'))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('preferred_from')
                    ->label(__('app/box_inquiry.field.preferred_from'))
                    ->date('d.m.Y')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('app/box_inquiry.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('app/box_inquiry.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        BoxInquiry::STATUS_NEW => 'warning',
                        BoxInquiry::STATUS_CONTACTED => 'info',
                        BoxInquiry::STATUS_CLOSED => 'success',
                        BoxInquiry::STATUS_SPAM => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('app/box_inquiry.field.status'))
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    /** @return array<string,string> */
    private static function statusOptions(): array
    {
        return [
            BoxInquiry::STATUS_NEW => __('app/box_inquiry.status.'.BoxInquiry::STATUS_NEW),
            BoxInquiry::STATUS_CONTACTED => __('app/box_inquiry.status.'.BoxInquiry::STATUS_CONTACTED),
            BoxInquiry::STATUS_CLOSED => __('app/box_inquiry.status.'.BoxInquiry::STATUS_CLOSED),
            BoxInquiry::STATUS_SPAM => __('app/box_inquiry.status.'.BoxInquiry::STATUS_SPAM),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoxInquiries::route('/'),
            'edit' => Pages\EditBoxInquiry::route('/{record}/edit'),
        ];
    }
}
