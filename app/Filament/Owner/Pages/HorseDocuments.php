<?php

declare(strict_types=1);

namespace App\Filament\Owner\Pages;

use App\Domain\Files\Owner\OwnerDocumentsService;
use App\Domain\Files\Owner\Snapshots\HorseDocumentSnapshot;
use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Enums\HorseDocumentKind;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Owner panel: lista dokumentów konia (paszport, kontrakt, ubezpieczenie,
 * świadectwa). Tabela z badge'ami "kto wgrał" + "wygasł"/"wygasa wkrótce".
 * Upload form z FileUpload + kind select + valid_from/until DatePickers.
 *
 * URL: /owner/horses/{centralHorseId}/documents
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 5".
 */
class HorseDocuments extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'horses/{centralHorseId}/documents';

    protected static string $view = 'filament.owner.pages.horse-documents';

    protected static bool $shouldRegisterNavigation = false;

    public string $centralHorseId = '';

    public ?Tenant $stableTenant = null;

    public ?HorseBoardingAssignment $assignment = null;

    /** @var list<HorseDocumentSnapshot> */
    public array $documents = [];

    /** @var array<string,mixed> */
    public array $data = [
        'name' => '',
        'kind' => 'other',
        'description' => null,
        'valid_from' => null,
        'valid_until' => null,
        'file' => null,
    ];

    public bool $canUpload = false;

    public function getTitle(): string|Htmlable
    {
        return __('owner/documents.page.title');
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/owner/horses') => __('owner/horses.navigation'),
            url('/owner/horses/'.$this->centralHorseId.'/details') => __('owner/horse_detail.breadcrumb'),
            __('owner/documents.page.breadcrumb') => '',
        ];
    }

    public function mount(string $centralHorseId): void
    {
        $this->centralHorseId = $centralHorseId;
        $user = Auth::user();
        abort_unless($user !== null, 401);

        try {
            $active = app(HorseOwnerStableAccessGate::class)->tryAuthorize($user, $centralHorseId);
        } catch (AuthorizationException) {
            abort(403, __('owner/documents.access.not_owner'));
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
                abort(403, __('owner/documents.access.not_owner'));
            }
            $this->assignment = $ended;
        }

        $this->stableTenant = Tenant::query()->find($this->assignment->stable_tenant_id);
        abort_unless($this->stableTenant !== null, 404);

        $this->form->fill($this->data);
        $this->loadDocuments();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('owner/documents.form.section'))
                    ->disabled(! $this->canUpload)
                    ->description($this->canUpload ? null : __('owner/documents.access.upload_requires_active_boarding'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('owner/documents.form.name'))
                            ->required()
                            ->maxLength(200),
                        Forms\Components\Select::make('kind')
                            ->label(__('owner/documents.form.kind'))
                            ->required()
                            ->options($this->kindOptions())
                            ->native(false),
                        Forms\Components\DatePicker::make('valid_from')
                            ->label(__('owner/documents.form.valid_from'))
                            ->native(false),
                        Forms\Components\DatePicker::make('valid_until')
                            ->label(__('owner/documents.form.valid_until'))
                            ->native(false)
                            ->afterOrEqual('valid_from'),
                        Forms\Components\Textarea::make('description')
                            ->label(__('owner/documents.form.description'))
                            ->rows(2)
                            ->columnSpanFull()
                            ->maxLength(1000),
                        Forms\Components\FileUpload::make('file')
                            ->label(__('owner/documents.form.file'))
                            ->required()
                            ->columnSpanFull()
                            ->maxSize(25 * 1024)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/jpeg', 'image/png', 'image/webp',
                            ])
                            ->storeFiles(false),
                    ]),
            ]);
    }

    public function upload(): void
    {
        if (! $this->canUpload) {
            Notification::make()->danger()->title(__('owner/documents.access.upload_requires_active_boarding'))->send();

            return;
        }
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $state = $this->form->getState();
        $file = $state['file'] ?? null;
        if (is_array($file)) {
            $file = array_values($file)[0] ?? null;
        }
        if (! $file instanceof UploadedFile) {
            Notification::make()->danger()->title(__('owner/documents.form.no_file'))->send();

            return;
        }

        try {
            app(OwnerDocumentsService::class)->upload(
                $user,
                $this->centralHorseId,
                $file,
                (string) $state['kind'],
                (string) $state['name'],
                isset($state['description']) && $state['description'] !== '' ? (string) $state['description'] : null,
                ! empty($state['valid_from']) ? Carbon::parse((string) $state['valid_from']) : null,
                ! empty($state['valid_until']) ? Carbon::parse((string) $state['valid_until']) : null,
            );
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (ValidationException $e) {
            Notification::make()->danger()
                ->title(__('owner/documents.form.upload_failed'))
                ->body(implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $e->errors())))
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title(__('owner/documents.form.upload_failed'))->body($e->getMessage())->send();

            return;
        }

        $this->data = ['name' => '', 'kind' => 'other', 'description' => null, 'valid_from' => null, 'valid_until' => null, 'file' => null];
        $this->form->fill($this->data);
        $this->loadDocuments();
        Notification::make()->success()->title(__('owner/documents.form.uploaded'))->send();
    }

    public function deleteDocument(string $documentId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        if ($this->stableTenant === null) {
            return;
        }

        try {
            app(OwnerDocumentsService::class)->delete($user, $this->stableTenant->id, $documentId);
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $this->loadDocuments();
        Notification::make()->success()->title(__('owner/documents.form.deleted'))->send();
    }

    public function downloadUrl(HorseDocumentSnapshot $doc): string
    {
        return url(sprintf(
            '/api/owner/documents/%s/%s/download',
            $doc->stableTenantId,
            $doc->id,
        ));
    }

    public function canDelete(HorseDocumentSnapshot $doc): bool
    {
        return $doc->uploadedByRole === 'client' && $this->canUpload;
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

    public function kindLabel(string $kindValue): string
    {
        $key = 'enums.horse_document_kind.'.$kindValue;
        $translated = __($key);

        return $translated === $key ? $kindValue : $translated;
    }

    private function loadDocuments(): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->documents = [];

            return;
        }
        try {
            $this->documents = app(OwnerDocumentsService::class)
                ->listForHorse($user, $this->centralHorseId);
        } catch (Throwable) {
            $this->documents = [];
        }
    }

    /** @return array<string, string> */
    private function kindOptions(): array
    {
        $out = [];
        foreach (HorseDocumentKind::cases() as $case) {
            $out[$case->value] = $this->kindLabel($case->value);
        }

        return $out;
    }
}
