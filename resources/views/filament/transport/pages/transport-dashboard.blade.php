@php
    $counts = $this->getHeroCounts();
    $checklist = $this->getOnboardingChecklist();
    $isChecklistComplete = $checklist['completed_count'] === $checklist['total_count'];
@endphp

<x-filament-panels::page>
    {{-- Hero CTA grid — 4 duże karty (Calculator / Inbox / Quotes / Invoices) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- 1. Wyceń trasę — kalkulator (zawsze widoczny, primary CTA) --}}
        <a href="{{ route('filament.transport.pages.calculator') }}"
           class="group block p-5 bg-primary-50 dark:bg-primary-900/20 border-2 border-primary-300 dark:border-primary-700 rounded-xl hover:border-primary-500 hover:shadow-md transition-all">
            <div class="flex items-start justify-between mb-2">
                <x-filament::icon icon="heroicon-o-calculator" class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                <span class="text-[10px] uppercase tracking-wide font-bold text-primary-700 dark:text-primary-300 bg-primary-100 dark:bg-primary-900/50 px-2 py-0.5 rounded">
                    {{ __('transport/dashboard.hero.primary_badge') }}
                </span>
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                {{ __('transport/dashboard.hero.calculator.title') }}
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('transport/dashboard.hero.calculator.body') }}
            </p>
        </a>

        {{-- 2. Inbox zapytań — z licznikiem unseen --}}
        <a href="{{ route('filament.transport.resources.leads.index') }}"
           class="group block p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-400 hover:shadow-md transition-all">
            <div class="flex items-start justify-between mb-2">
                <x-filament::icon icon="heroicon-o-inbox-arrow-down" class="w-7 h-7 text-blue-600 dark:text-blue-400" />
                @if ($counts['unseen_leads'] > 0)
                    <span class="text-xs font-bold text-white bg-blue-600 px-2 py-0.5 rounded-full">
                        {{ $counts['unseen_leads'] }}
                    </span>
                @endif
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                {{ __('transport/dashboard.hero.leads.title') }}
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                @if ($counts['unseen_leads'] > 0)
                    {{ trans_choice('transport/dashboard.hero.leads.body_with_count', $counts['unseen_leads'], ['count' => $counts['unseen_leads']]) }}
                @else
                    {{ __('transport/dashboard.hero.leads.body_empty') }}
                @endif
            </p>
        </a>

        {{-- 3. Oferty wysłane — czekają na akceptację klienta --}}
        <a href="{{ route('filament.transport.resources.quotes.index') }}"
           class="group block p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-400 hover:shadow-md transition-all">
            <div class="flex items-start justify-between mb-2">
                <x-filament::icon icon="heroicon-o-document-text" class="w-7 h-7 text-amber-600 dark:text-amber-400" />
                @if ($counts['pending_quotes'] > 0)
                    <span class="text-xs font-bold text-white bg-amber-600 px-2 py-0.5 rounded-full">
                        {{ $counts['pending_quotes'] }}
                    </span>
                @endif
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                {{ __('transport/dashboard.hero.quotes.title') }}
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                @if ($counts['pending_quotes'] > 0)
                    {{ trans_choice('transport/dashboard.hero.quotes.body_with_count', $counts['pending_quotes'], ['count' => $counts['pending_quotes']]) }}
                @else
                    {{ __('transport/dashboard.hero.quotes.body_empty') }}
                @endif
            </p>
        </a>

        {{-- 4. Faktury nieopłacone --}}
        <a href="{{ route('filament.transport.resources.transport-invoices.index') }}"
           class="group block p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-400 hover:shadow-md transition-all">
            <div class="flex items-start justify-between mb-2">
                <x-filament::icon icon="heroicon-o-banknotes" class="w-7 h-7 text-emerald-600 dark:text-emerald-400" />
                @if ($counts['unpaid_invoices'] > 0)
                    <span class="text-xs font-bold text-white bg-emerald-600 px-2 py-0.5 rounded-full">
                        {{ $counts['unpaid_invoices'] }}
                    </span>
                @endif
            </div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                {{ __('transport/dashboard.hero.invoices.title') }}
            </h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                @if ($counts['unpaid_invoices'] > 0)
                    {{ __('transport/dashboard.hero.invoices.body_with_amount', [
                        'amount' => number_format($counts['unpaid_invoices_cents'] / 100, 0, ',', ' ').' zł',
                    ]) }}
                @else
                    {{ __('transport/dashboard.hero.invoices.body_empty') }}
                @endif
            </p>
        </a>
    </div>

    {{-- Onboarding checklist — pokazujemy tylko gdy niekompletna. Po
         zakończeniu znika, żeby nie zaśmiecać dashboardu doświadczonemu
         transporterowi. --}}
    @unless ($isChecklistComplete)
        <div class="mt-6 p-5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-amber-900 dark:text-amber-100">
                    {{ __('transport/dashboard.onboarding.heading') }}
                </h3>
                <span class="text-xs font-bold text-amber-700 dark:text-amber-300">
                    {{ $checklist['completed_count'] }} / {{ $checklist['total_count'] }}
                </span>
            </div>
            <p class="text-sm text-amber-800 dark:text-amber-200 mb-3 leading-relaxed">
                {{ __('transport/dashboard.onboarding.intro') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {{-- 1. Weryfikacja dokumentów --}}
                <a href="{{ route('filament.transport.pages.transporter-documents') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 transition-colors">
                    @if ($checklist['verified'])
                        <x-filament::icon icon="heroicon-s-check-circle" class="w-5 h-5 text-emerald-500" />
                        <span class="text-sm text-gray-500 dark:text-gray-400 line-through">{{ __('transport/dashboard.onboarding.step.verified') }}</span>
                    @else
                        <x-filament::icon icon="heroicon-o-document-check" class="w-5 h-5 text-amber-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('transport/dashboard.onboarding.step.verify') }}</span>
                    @endif
                </a>

                {{-- 2. Dodaj pojazd --}}
                <a href="{{ route('filament.transport.resources.vehicles.index') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 transition-colors">
                    @if ($checklist['has_vehicles'])
                        <x-filament::icon icon="heroicon-s-check-circle" class="w-5 h-5 text-emerald-500" />
                        <span class="text-sm text-gray-500 dark:text-gray-400 line-through">{{ __('transport/dashboard.onboarding.step.vehicles_done') }}</span>
                    @else
                        <x-filament::icon icon="heroicon-o-truck" class="w-5 h-5 text-amber-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('transport/dashboard.onboarding.step.add_vehicle') }}</span>
                    @endif
                </a>

                {{-- 3. Dodaj kierowcę --}}
                <a href="{{ route('filament.transport.resources.drivers.index') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 transition-colors">
                    @if ($checklist['has_drivers'])
                        <x-filament::icon icon="heroicon-s-check-circle" class="w-5 h-5 text-emerald-500" />
                        <span class="text-sm text-gray-500 dark:text-gray-400 line-through">{{ __('transport/dashboard.onboarding.step.drivers_done') }}</span>
                    @else
                        <x-filament::icon icon="heroicon-o-user-plus" class="w-5 h-5 text-amber-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('transport/dashboard.onboarding.step.add_driver') }}</span>
                    @endif
                </a>

                {{-- 4. Obszary działania --}}
                <a href="{{ route('filament.transport.pages.service-areas') }}"
                   class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-400 transition-colors">
                    @if ($checklist['has_service_areas'])
                        <x-filament::icon icon="heroicon-s-check-circle" class="w-5 h-5 text-emerald-500" />
                        <span class="text-sm text-gray-500 dark:text-gray-400 line-through">{{ __('transport/dashboard.onboarding.step.service_areas_done') }}</span>
                    @else
                        <x-filament::icon icon="heroicon-o-map" class="w-5 h-5 text-amber-500" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ __('transport/dashboard.onboarding.step.set_service_areas') }}</span>
                    @endif
                </a>
            </div>
        </div>
    @endunless

    {{-- Existing widgets — KPI, upcoming transports, top corridors, etc. --}}
    <div class="mt-6">
        <x-filament-widgets::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
            :data="['filters' => $this->filters ?? null]"
        />
    </div>
</x-filament-panels::page>
