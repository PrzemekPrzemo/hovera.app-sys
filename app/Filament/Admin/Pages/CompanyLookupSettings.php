<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Central\SystemSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Master-admin: GUS / KRS configuration. KRS is public and works without
 * any setup; GUS requires an API key (issued by stat.gov.pl after
 * registration, free for development).
 *
 * Per-stable lookup uses these globally-configured creds — pojedyncza
 * konfiguracja dla całego systemu Hovera.
 */
class CompanyLookupSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    public static function getNavigationLabel(): string
    {
        return __('pages.company_lookup.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.configuration');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.company_lookup.title');
    }

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.admin.pages.company-lookup-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gus_api_key' => SystemSetting::getSecret('gus.api_key', '') ?? '',
            'gus_env' => SystemSetting::getValue('gus.env', 'test'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make(__('admin/company_lookup.form.section.gus'))
                    ->description(__('admin/company_lookup.form.section.gus_description'))
                    ->schema([
                        Forms\Components\TextInput::make('gus_api_key')
                            ->label(__('admin/company_lookup.form.label.gus_api_key'))
                            ->password()
                            ->revealable()
                            ->helperText(__('admin/company_lookup.form.helper.gus_api_key')),
                        Forms\Components\Radio::make('gus_env')
                            ->label(__('admin/company_lookup.form.label.gus_env'))
                            ->options([
                                'test' => __('admin/company_lookup.form.options.env_test'),
                                'prod' => __('admin/company_lookup.form.options.env_prod'),
                            ])
                            ->default('test')
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('admin/company_lookup.form.section.krs'))
                    ->description(__('admin/company_lookup.form.section.krs_description'))
                    ->schema([
                        Forms\Components\Placeholder::make('krs_status')
                            ->label(__('admin/company_lookup.form.label.krs_status'))
                            ->content(__('admin/company_lookup.form.options.krs_enabled')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $form = $this->form->getState();

        $key = trim((string) ($form['gus_api_key'] ?? ''));
        if ($key !== '') {
            SystemSetting::setSecret('gus.api_key', $key);
        }
        SystemSetting::setValue('gus.env', (string) ($form['gus_env'] ?? 'test'));

        Notification::make()->title(__('admin/company_lookup.action.saved'))->success()->send();
    }
}
