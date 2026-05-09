<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Filament\Concerns\RestrictedByTenantRole;
use App\Services\Import\ExcelImportService;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 4-step Wizard:
 *   1. Wybór typu (Klienci / Konie)
 *   2. Plik (.xlsx / .xls / .csv, max 10 MB)
 *   3. Mapowanie kolumn (auto-suggest po nazwie nagłówka)
 *   4. Podgląd + import
 *
 * Dane (headers + rows) trzymamy w polu Livewire `parsed`. Plik tymczasowy
 * leży w `storage/app/livewire-tmp` — Filament FileUpload sam zarządza
 * cyklem życia.
 */
class ImportWizard extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FULL_ADMINS_AND_MANAGERS;
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.app.pages.import-wizard';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.tools');
    }

    public static function getNavigationLabel(): string
    {
        return __('import-wizard.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('import-wizard.title');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array{headers: list<string>, rows: list<array<int, scalar|null>>}|null */
    public ?array $parsed = null;

    /** @var array<string, string|null>|null */
    public ?array $suggestedMapping = null;

    /** @var array{imported:int, failed:int, errors:list<array{row:int, message:string}>}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill([
            'entity' => ExcelImportService::ENTITY_CLIENTS,
            'file' => null,
            'mapping' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        $service = app(ExcelImportService::class);

        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('import-wizard.steps.entity.title'))
                        ->description(__('import-wizard.steps.entity.description'))
                        ->schema([
                            Forms\Components\Radio::make('entity')
                                ->label(__('import-wizard.fields.entity'))
                                ->options([
                                    ExcelImportService::ENTITY_CLIENTS => __('import-wizard.entity.clients'),
                                    ExcelImportService::ENTITY_HORSES => __('import-wizard.entity.horses'),
                                ])
                                ->descriptions([
                                    ExcelImportService::ENTITY_CLIENTS => __('import-wizard.entity.clients_hint'),
                                    ExcelImportService::ENTITY_HORSES => __('import-wizard.entity.horses_hint'),
                                ])
                                ->required()
                                ->live(),
                        ]),

                    Forms\Components\Wizard\Step::make(__('import-wizard.steps.file.title'))
                        ->description(__('import-wizard.steps.file.description'))
                        ->schema([
                            Forms\Components\FileUpload::make('file')
                                ->label(__('import-wizard.fields.file'))
                                ->disk('local')
                                ->directory('imports')
                                ->visibility('private')
                                ->acceptedFileTypes([
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'application/vnd.ms-excel',
                                    'text/csv',
                                    'text/plain',
                                ])
                                ->preserveFilenames()
                                ->maxSize(10 * 1024) // KB → 10 MB
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state) use ($service): void {
                                    if (! $state) {
                                        $this->parsed = null;

                                        return;
                                    }
                                    try {
                                        $upload = $this->resolveUpload($state);
                                        if ($upload === null) {
                                            return;
                                        }
                                        $this->parsed = $service->parseFile($upload);
                                        $this->suggestedMapping = $service->suggestMapping(
                                            $this->data['entity'] ?? ExcelImportService::ENTITY_CLIENTS,
                                            $this->parsed['headers']
                                        );
                                        $this->data['mapping'] = $this->suggestedMapping;
                                    } catch (\Throwable $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title(__('import-wizard.parse_failed'))
                                            ->body($e->getMessage())
                                            ->send();
                                        $this->parsed = null;
                                    }
                                }),
                            Forms\Components\Placeholder::make('parse_summary')
                                ->label('')
                                ->content(fn () => $this->parsed
                                    ? __('import-wizard.parse_summary', [
                                        'rows' => count($this->parsed['rows']),
                                        'cols' => count($this->parsed['headers']),
                                    ])
                                    : __('import-wizard.parse_pending')),
                        ]),

                    Forms\Components\Wizard\Step::make(__('import-wizard.steps.mapping.title'))
                        ->description(__('import-wizard.steps.mapping.description'))
                        ->schema(fn () => $this->buildMappingSchema()),

                    Forms\Components\Wizard\Step::make(__('import-wizard.steps.preview.title'))
                        ->description(__('import-wizard.steps.preview.description'))
                        ->schema([
                            Forms\Components\Placeholder::make('preview_table')
                                ->label('')
                                ->content(fn () => view('filament.app.pages.partials.import-preview', [
                                    'rows' => $this->previewRows(),
                                    'mapping' => $this->data['mapping'] ?? [],
                                ])),
                        ]),
                ])
                    ->submitAction(view('filament.app.pages.partials.import-submit', [
                        'label' => __('import-wizard.actions.import'),
                    ])),
            ])
            ->statePath('data');
    }

    /**
     * @return list<Component>
     */
    private function buildMappingSchema(): array
    {
        if ($this->parsed === null) {
            return [
                Forms\Components\Placeholder::make('no_file')
                    ->label('')
                    ->content(__('import-wizard.upload_first')),
            ];
        }

        $entity = $this->data['entity'] ?? ExcelImportService::ENTITY_CLIENTS;
        $aliases = ExcelImportService::HEADER_ALIASES[$entity] ?? [];
        $headerOptions = ['' => '— '.__('import-wizard.skip').' —'];
        foreach ($this->parsed['headers'] as $h) {
            $headerOptions[$h] = $h;
        }

        $components = [];
        foreach (array_keys($aliases) as $field) {
            $components[] = Forms\Components\Select::make("mapping.$field")
                ->label(__('import-wizard.fields.'.$entity.'.'.$field, [], app()->getLocale()) ?: $field)
                ->options($headerOptions)
                ->default($this->suggestedMapping[$field] ?? null)
                ->native(false);
        }

        return $components;
    }

    /**
     * Build the preview slice: first 5 rows, validated.
     *
     * @return list<array{ok:bool, errors:list<string>, data:array<string,mixed>}>
     */
    public function previewRows(): array
    {
        if ($this->parsed === null) {
            return [];
        }
        $service = app(ExcelImportService::class);
        $entity = $this->data['entity'] ?? ExcelImportService::ENTITY_CLIENTS;
        $mapping = $this->data['mapping'] ?? [];

        $rows = array_slice($this->parsed['rows'], 0, 5);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $service->validateRow($entity, $mapping, $row, $this->parsed['headers']);
        }

        return $out;
    }

    public function runImport(): void
    {
        if ($this->parsed === null) {
            Notification::make()->danger()->title(__('import-wizard.no_file'))->send();

            return;
        }

        $entity = $this->data['entity'] ?? ExcelImportService::ENTITY_CLIENTS;
        $mapping = $this->data['mapping'] ?? [];

        $service = app(ExcelImportService::class);
        $this->result = $service->import($entity, $mapping, $this->parsed['rows'], $this->parsed['headers']);

        Notification::make()
            ->title(__('import-wizard.flash.success', ['count' => $this->result['imported']]))
            ->body(__('import-wizard.flash.failed', ['count' => $this->result['failed']]))
            ->success()
            ->send();
    }

    /**
     * Filament FileUpload state can be either an array of TemporaryUploadedFile
     * objects keyed by ULID (during the upload), or a list of stored filenames
     * after persistence. Resolve the actual file we need to parse.
     */
    private function resolveUpload(mixed $state): ?UploadedFile
    {
        if (is_array($state)) {
            $first = reset($state);
            if ($first instanceof UploadedFile) {
                return $first;
            }
            if (is_string($first)) {
                $disk = Storage::disk('local');
                if (! $disk->exists($first)) {
                    return null;
                }

                return new UploadedFile($disk->path($first), basename($first), null, null, true);
            }
        }
        if ($state instanceof UploadedFile) {
            return $state;
        }
        if (is_string($state)) {
            $disk = Storage::disk('local');
            if ($disk->exists($state)) {
                return new UploadedFile($disk->path($state), basename($state), null, null, true);
            }
        }

        return null;
    }
}
