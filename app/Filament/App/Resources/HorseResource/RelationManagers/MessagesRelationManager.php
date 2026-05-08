<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\RelationManagers;

use App\Actions\Stable\SendHorseMessage;
use App\Models\Tenant\Horse;
use App\Models\Tenant\HorseMessage;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Tab "Wiadomości" w karcie konia (`/app → Konie → {koń}`).
 *
 * Stajnia może:
 *   - Czytać thread w obu kierunkach (badge "nieprzeczytane od klienta")
 *   - Wysłać wiadomość do właściciela (z attachmentami)
 *   - Oznaczyć przychodzące jako odczytane (poprzez otwarcie ViewAction)
 *
 * Properties Filamentu — UI minimalistyczne, czas na piękno UI sprawiamy
 * w portalu klienta (sekcja 'Wiadomości' będzie ładniejsza tam).
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('models.messages');
    }

    public static function getModelLabel(): ?string
    {
        return __('models.message');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('models.messages');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('direction')->default('from_stable'),
            Forms\Components\TextInput::make('subject')
                ->label(__('app/horse_message.form.label.subject'))->maxLength(200),
            Forms\Components\Textarea::make('body')
                ->label(__('app/horse_message.form.label.body'))->rows(4)->required(),
            Forms\Components\FileUpload::make('attachments_upload')
                ->label(__('app/horse_message.form.label.attachments'))
                ->multiple()
                ->maxFiles(5)
                ->maxSize(10 * 1024) // KB
                ->acceptedFileTypes(['image/*', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->disk('local')
                ->visibility('private')
                ->storeFiles(false), // przekazujemy raw do save handlera
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('app/horse_message.table.column.sent_at'))
                    ->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('direction')
                    ->label(__('app/horse_message.table.column.direction'))
                    ->formatStateUsing(fn (string $state) => $state === 'from_stable'
                        ? __('app/horse_message.directions.from_stable')
                        : __('app/horse_message.directions.from_client'))
                    ->colors([
                        'primary' => 'from_stable',
                        'warning' => 'from_client',
                    ]),
                Tables\Columns\TextColumn::make('subject')
                    ->label(__('app/horse_message.table.column.subject'))->placeholder('—')->limit(40),
                Tables\Columns\TextColumn::make('body')
                    ->label(__('app/horse_message.table.column.body'))->limit(60),
                Tables\Columns\TextColumn::make('attachments')
                    ->label(__('app/horse_message.table.column.attachments_short'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? '📎 '.count($state) : ''),
                Tables\Columns\IconColumn::make('read_by_stable_at')
                    ->label(__('app/horse_message.table.column.read_short'))
                    ->boolean()
                    ->getStateUsing(fn (HorseMessage $r) => ! ($r->isFromClient() && $r->read_by_stable_at === null)),
            ])
            ->defaultSort('sent_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('app/horse_message.action.create.label'))
                    ->icon('heroicon-m-paper-airplane')
                    ->using(function (array $data): HorseMessage {
                        /** @var Horse $horse */
                        $horse = $this->getOwnerRecord();
                        $tenant = app(TenantManager::class)->tenantOrFail();
                        $files = (array) ($data['attachments_upload'] ?? []);

                        try {
                            return app(SendHorseMessage::class)->fromStable(
                                tenant: $tenant,
                                horse: $horse,
                                body: (string) $data['body'],
                                subject: (string) ($data['subject'] ?? '') ?: null,
                                senderUserId: Auth::id() ? (string) Auth::id() : null,
                                attachments: $this->normaliseUploadedFiles($files),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()->title(__('app/horse_message.action.create.failed'))->body(
                                implode("\n", Arr::flatten($e->errors())),
                            )->danger()->send();
                            throw $e;
                        }
                    })
                    ->successNotification(
                        Notification::make()->title(__('app/horse_message.action.create.sent'))->success(),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label(__('app/horse_message.action.mark_read.label'))
                    ->icon('heroicon-m-check')
                    ->visible(fn (HorseMessage $r) => $r->isUnreadByStable())
                    ->action(function (HorseMessage $record) {
                        $record->forceFill(['read_by_stable_at' => now()])->save();
                        Notification::make()->title(__('app/horse_message.action.mark_read.success'))->success()->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->after(function (HorseMessage $record) {
                        if ($record->isUnreadByStable()) {
                            $record->forceFill(['read_by_stable_at' => now()])->save();
                        }
                    }),
            ]);
    }

    /**
     * @param  array<mixed>  $files
     * @return array<int, UploadedFile>
     */
    private function normaliseUploadedFiles(array $files): array
    {
        $out = [];
        foreach ($files as $f) {
            if ($f instanceof UploadedFile) {
                $out[] = $f;
            } elseif ($f instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                // Wrap żeby zachować spójny typ
                $out[] = new UploadedFile(
                    $f->getRealPath(),
                    $f->getClientOriginalName(),
                    $f->getMimeType(),
                    null,
                    true,
                );
            }
        }

        return $out;
    }
}
