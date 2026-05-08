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
                Forms\Components\Section::make('GUS BIR (REGON)')
                    ->description('API kluczyk wydaje GUS po rejestracji na https://api.stat.gov.pl. Bezpłatny. Klucz rotuje co kwartał — pamiętaj o wymianie.')
                    ->schema([
                        Forms\Components\TextInput::make('gus_api_key')
                            ->label('Klucz API GUS')
                            ->password()
                            ->revealable()
                            ->helperText('Klucz testowy z dokumentacji GUS: abcde12345abcde12345 (działa tylko ze środowiskiem test).'),
                        Forms\Components\Radio::make('gus_env')
                            ->label('Środowisko')
                            ->options([
                                'test' => 'Test (wyszukiwarkaregontest.stat.gov.pl)',
                                'prod' => 'Produkcyjne (wyszukiwarkaregon.stat.gov.pl)',
                            ])
                            ->default('test')
                            ->required(),
                    ]),

                Forms\Components\Section::make('KRS (publiczne API)')
                    ->description('KRS Open Data API jest publiczne i nie wymaga konfiguracji. Hovera korzysta z https://api-krs.ms.gov.pl. Cache 30 dni.')
                    ->schema([
                        Forms\Components\Placeholder::make('krs_status')
                            ->label('Status')
                            ->content('✓ Włączone (publiczne API, brak konfiguracji)'),
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

        Notification::make()->title('Zapisano konfigurację GUS / KRS')->success()->send();
    }
}
