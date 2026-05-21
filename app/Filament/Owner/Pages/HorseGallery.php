<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Files\Owner\OwnerPhotosService;
use App\Domain\Files\Owner\Snapshots\HorsePhotoSnapshot;
use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
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
use Throwable;

/**
 * Owner panel: galeria zdjęć konia. Lista (stable + own uploads) z
 * badge "kto wgrał", upload formy (multipart przez Filament FileUpload),
 * delete swoich.
 *
 * URL: /owner/horses/{centralHorseId}/photos
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class HorseGallery extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'horses/{centralHorseId}/photos';

    protected static string $view = 'filament.owner.pages.horse-gallery';

    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    /** @var list<HorsePhotoSnapshot> */
    public array $photos = [];

    /** @var array<string,mixed> */
    public array $data = [
        'caption' => null,
        'file' => null,
    ];

    public bool $canUpload = false;

    public function getTitle(): string|Htmlable
    {
        return __('owner/photos.page.title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            url('/owner/horses/'.$this->centralHorseId.'/details') => __('owner/horse_detail.breadcrumb'),
            __('owner/photos.page.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        // Gate: primary_owner + active/ended boarding (Q3 — ended pozwala
        // read-only). canUpload tylko gdy ACTIVE.
        try {
            $active = app(HorseOwnerStableAccessGate::class)->tryAuthorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/photos.access.not_owner'));
        }

        $this->assignment = $active;
        $this->canUpload = $active !== null;

        if ($this->assignment === null) {
            $ended = HorseBoardingAssignment::query()
                ->where('central_horse_id', $centralHorseId)
                ->where('owner_user_id', $user->id)
                ->where('status', HorseBoardingAssignment::STATUS_ENDED)
                ->latest('started_at')
                ->first();
            if ($ended === null) {
                abort(403, __('owner/photos.access.not_owner'));
            }
            $this->assignment = $ended;
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        $this->form->fill($this->data);
        $this->loadPhotos();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('owner/photos.form.section'))
                    ->disabled(! $this->canUpload)
                    ->description($this->canUpload ? null : __('owner/photos.access.upload_requires_active_boarding'))
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label(__('owner/photos.form.file'))
                            ->required()
                            ->image()
                            ->imageEditor()
                            ->maxSize(10 * 1024)  // KB → 10 MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->storeFiles(false),
                        Forms\Components\TextInput::make('caption')
                            ->label(__('owner/photos.form.caption'))
                            ->maxLength(500),
                    ]),
            ]);
    }

    public function upload(): void
    {
        if (! $this->canUpload) {
            Notification::make()
                ->danger()
                ->title(__('owner/photos.access.upload_requires_active_boarding'))
                ->send();

            return;
        }

        $state = $this->form->getState();
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $file = $state['file'] ?? null;
        // Filament FileUpload z storeFiles(false) zwraca array gdy multiple,
        // single value gdy nie. Normalizujemy.
        if (is_array($file)) {
            $file = array_values($file)[0] ?? null;
        }

        if (! $file instanceof UploadedFile) {
            Notification::make()
                ->danger()
                ->title(__('owner/photos.form.no_file'))
                ->send();

            return;
        }

        try {
            app(OwnerPhotosService::class)->upload(
                $user,
                $this->centralHorseId,
                $file,
                $state['caption'] !== '' ? $state['caption'] : null,
            );
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title(__('owner/photos.form.upload_failed'))
                ->body(implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $e->errors())))
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('owner/photos.form.upload_failed'))->body($e->getMessage())->send();

            return;
        }

        // Reset + reload
        $this->data = ['caption' => null, 'file' => null];
        $this->form->fill($this->data);
        $this->loadPhotos();

        Notification::make()
            ->success()
            ->title(__('owner/photos.form.uploaded'))
            ->send();
    }

    public function deletePhoto(string $photoId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        if ($this->stableTenant === null) {
            return;
        }

        try {
            app(OwnerPhotosService::class)->delete($user, $this->stableTenant->id, $photoId);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->loadPhotos();
        Notification::make()->success()->title(__('owner/photos.form.deleted'))->send();
    }

    /**
     * URL do download endpoint'a — używany do thumb + lightbox.
     */
    public function downloadUrl(HorsePhotoSnapshot $photo): string
    {
        return url(sprintf(
            '/api/owner/photos/%s/%s/download',
            $photo->stableTenantId,
            $photo->id,
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
     * Czy user może skasować dane zdjęcie. Tylko swoje (uploaded_by_role=
     * 'client') i tylko gdy active boarding.
     */
    public function canDelete(HorsePhotoSnapshot $photo): bool
    {
        return $photo->uploadedByRole === 'client' && $this->canUpload;
    }

    private function loadPhotos(): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->photos = [];

            return;
        }
        try {
            $this->photos = app(OwnerPhotosService::class)
                ->listForHorse($user, $this->centralHorseId);
        } catch (Throwable) {
            $this->photos = [];
        }
    }
}
