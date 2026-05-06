<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Dane konta</x-slot>
            <x-slot name="description">
                Email służy do logowania i nie da się go zmienić samodzielnie. Jeśli musisz, skontaktuj się z administratorem.
            </x-slot>

            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">Zapisz</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Zmiana hasła</x-slot>
            <x-slot name="description">
                Po zmianie hasła pozostaniesz zalogowany w bieżącej sesji.
            </x-slot>

            <form wire:submit="changePassword">
                {{ $this->passwordForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">Zmień hasło</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @php
            $user = auth()->user();
            $hasTwoFactor = $user->hasTwoFactorEnabled();
        @endphp

        <x-filament::section>
            <x-slot name="heading">Uwierzytelnianie dwuskładnikowe (2FA)</x-slot>
            <x-slot name="description">
                @if ($hasTwoFactor)
                    Twoje konto jest zabezpieczone TOTP (Google Authenticator / 1Password / Authy).
                @else
                    Zalecamy włączenie 2FA dla dodatkowego zabezpieczenia konta.
                @endif
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if ($hasTwoFactor && $user->is_master_admin)
                    2FA jest <strong>wymagane</strong> dla konta master admina i nie można go wyłączyć z poziomu profilu.
                @elseif ($hasTwoFactor)
                    Status: <strong class="text-success-600">aktywne</strong>.
                @else
                    Status: <strong class="text-warning-600">nieaktywne</strong>.
                @endif
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
