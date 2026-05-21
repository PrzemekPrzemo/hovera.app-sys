<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Domain\Messages\Owner\HorseMessageAttachmentStorage;
use App\Domain\Messages\Owner\OwnerMessagesService;
use App\Domain\Messages\Owner\Snapshots\HorseMessageSnapshot;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Owner panel: chat thread per koń. Lista wiadomości (oba kierunki) +
 * form wysyłania nowej wiadomości z attachments. Auto-mark-read przy
 * mount'cie (wszystkie unread from_stable → read).
 *
 * URL: /owner/horses/{centralHorseId}/messages
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 4 PR 4.3".
 */
class HorseMessages extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'horses/{centralHorseId}/messages';

    protected static string $view = 'filament.owner.pages.horse-messages';

    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    /** @var list<HorseMessageSnapshot> */
    public array $thread = [];

    /** @var array<string, mixed> */
    public array $data = [
        'subject' => null,
        'body' => '',
        'files' => [],
    ];

    public bool $canSend = false;

    public function getTitle(): string|Htmlable
    {
        return __('owner/messages.page.title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            url('/owner/horses/'.$this->centralHorseId.'/details') => __('owner/horse_detail.breadcrumb'),
            __('owner/messages.page.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // Gate ownership — primary_owner_user_id w CentralHorseRegistry.
        // ALE pozwalamy read access nawet gdy tylko ended boarding (Q3).
        // canSend zostanie ustawione na true tylko gdy ACTIVE.
        try {
            // tryAuthorize zwraca null gdy brak active — pozwalamy dalej
            // (resolveStableFromAnyAssignment), ale send wyłączony.
            $activeAssignment = app(HorseOwnerStableAccessGate::class)
                ->tryAuthorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/messages.access.not_owner'));
        }

        $this->assignment = $activeAssignment;
        $this->canSend = $activeAssignment !== null;

        // Jeśli brak active — zobacz czy jest ended z którego można czytać.
        if ($this->assignment === null) {
            $endedAssignment = HorseBoardingAssignment::query()
                ->where('central_horse_id', $centralHorseId)
                ->where('owner_user_id', $user->id)
                ->whereIn('status', [HorseBoardingAssignment::STATUS_ENDED])
                ->latest('started_at')
                ->first();

            if ($endedAssignment === null) {
                abort(403, __('owner/messages.access.not_owner'));
            }
            $this->assignment = $endedAssignment;
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        $this->form->fill($this->data);
        $this->loadThread();
        $this->autoMarkReadOnLoad();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('owner/messages.form.section'))
                    ->disabled(! $this->canSend)
                    ->description($this->canSend ? null : __('owner/messages.access.send_requires_active_boarding'))
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label(__('owner/messages.form.subject'))
                            ->maxLength(200),
                        Forms\Components\Textarea::make('body')
                            ->label(__('owner/messages.form.body'))
                            ->required()
                            ->rows(4)
                            ->maxLength(10000),
                        Forms\Components\FileUpload::make('files')
                            ->label(__('owner/messages.form.attachments'))
                            ->helperText(__('owner/messages.form.attachments_hint'))
                            ->multiple()
                            ->maxFiles(10)
                            ->maxSize(25 * 1024)  // KB → 25 MB
                            ->acceptedFileTypes([
                                'image/jpeg', 'image/png', 'image/webp',
                                'application/pdf',
                                'video/mp4', 'video/quicktime',
                            ])
                            ->storeFiles(false),  // trzymamy w pamięci Livewire'a do submit'a
                    ]),
            ]);
    }

    public function send(): void
    {
        if (! $this->canSend) {
            Notification::make()
                ->danger()
                ->title(__('owner/messages.access.send_requires_active_boarding'))
                ->send();

            return;
        }

        $state = $this->form->getState();
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $body = trim((string) ($state['body'] ?? ''));
        if ($body === '') {
            Notification::make()
                ->danger()
                ->title(__('owner/messages.form.empty_body'))
                ->send();

            return;
        }

        // Upload plików najpierw — każdy przez attachment storage cross-tenant.
        $attachments = [];
        try {
            $attachments = $this->storeUploadedFiles($state['files'] ?? []);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title(__('owner/messages.form.attachments_failed'))
                ->body(implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $e->errors())))
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->danger()
                ->title(__('owner/messages.form.attachments_failed'))
                ->body($e->getMessage())
                ->send();

            return;
        }

        try {
            app(OwnerMessagesService::class)->send(
                $user,
                $this->centralHorseId,
                (string) ($state['subject'] ?? '') !== '' ? (string) $state['subject'] : null,
                $body,
                $attachments,
            );
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (RuntimeException $e) {
            report($e);
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        // Reset form + reload thread
        $this->data = ['subject' => null, 'body' => '', 'files' => []];
        $this->form->fill($this->data);
        $this->loadThread();

        Notification::make()
            ->success()
            ->title(__('owner/messages.form.sent_title'))
            ->send();
    }

    /**
     * Helper Blade — sprawdza czy attachment'em jest obrazek (do inline
     * preview tag'a img zamiast generic linka).
     */
    public function isImageAttachment(array $attachment): bool
    {
        $mime = (string) ($attachment['mime'] ?? '');

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    /**
     * Helper Blade — URL do download endpoint'a dla attachment'u na
     * pozycji `index` w wiadomości `messageId`.
     */
    public function downloadUrl(string $messageId, int $index): string
    {
        return url(sprintf(
            '/api/owner/messages/%s/%s/attachments/%d/download',
            $this->stableTenant->id,
            $messageId,
            $index,
        ));
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', ' ').' MB';
    }

    /**
     * Iteruje przez Livewire-uploaded files, każdy storeUje przez attachment
     * storage z cross-tenant execute (resolveuje horse w stable DB).
     *
     * @param  array<int, UploadedFile|mixed>  $files
     * @return list<array{path: string, original_name: string, mime: string, size: int, uploader: string}>
     */
    private function storeUploadedFiles(array $files): array
    {
        $tenants = app(TenantManager::class);
        $storage = app(HorseMessageAttachmentStorage::class);
        $stableTenant = $this->stableTenant;
        if ($stableTenant === null) {
            return [];
        }

        return $tenants->execute($stableTenant, function () use ($files, $storage, $stableTenant): array {
            $horse = Horse::query()
                ->where('central_horse_id', $this->centralHorseId)
                ->first();
            if ($horse === null) {
                throw new RuntimeException('Horse not found in stable DB');
            }

            $out = [];
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $out[] = $storage->storeUploadedFile(
                    $stableTenant,
                    (string) $horse->id,
                    $file,
                    HorseMessageAttachmentStorage::UPLOADER_OWNER,
                );
            }

            return $out;
        });
    }

    private function loadThread(): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->thread = [];

            return;
        }
        try {
            $this->thread = app(OwnerMessagesService::class)
                ->listForHorse($user, $this->centralHorseId);
        } catch (Throwable) {
            $this->thread = [];
        }
    }

    /**
     * Po wejściu na stronę markujemy wszystkie unread from_stable jako
     * przeczytane. Nav badge unread-count się zaktualizuje.
     */
    private function autoMarkReadOnLoad(): void
    {
        $user = Auth::user();
        if ($user === null || $this->stableTenant === null) {
            return;
        }

        $service = app(OwnerMessagesService::class);
        foreach ($this->thread as $entry) {
            if ($entry->isUnreadByOwner()) {
                $service->markRead($user, $this->stableTenant->id, $entry->id);
            }
        }
    }
}
