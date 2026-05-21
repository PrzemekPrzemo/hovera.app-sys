<x-filament-panels::page>
    @php($snapshot = $this->snapshot)
    @php($stable = $this->stableTenant)
    @php($assignment = $this->assignment)

    {{-- Hero banner: nazwa konia + stajnia w której boarduje --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                    {{ __('owner/horse_detail.hero.boarding_at') }}
                </div>
                <div class="text-lg font-semibold">{{ $stable->name }}</div>
            </div>
            @if ($assignment?->started_at)
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-gray-500">
                        {{ __('owner/horse_detail.hero.since') }}
                    </div>
                    <div class="font-medium">{{ $assignment->started_at->format('Y-m-d') }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Sekcja: identyfikacja --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold">{{ __('owner/horse_detail.section.identification') }}</h2>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.name') }}</dt>
                <dd class="font-medium">{{ $snapshot->name }}</dd>
            </div>
            @if ($snapshot->breed)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.breed') }}</dt>
                    <dd class="font-medium">{{ $snapshot->breed }}</dd>
                </div>
            @endif
            @if ($snapshot->sex)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.sex') }}</dt>
                    <dd class="font-medium">{{ __('owner/horses.sex.'.$snapshot->sex) }}</dd>
                </div>
            @endif
            @if ($snapshot->color)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.color') }}</dt>
                    <dd class="font-medium">{{ $snapshot->color }}</dd>
                </div>
            @endif
            @if ($snapshot->birthDate)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.birth_date') }}</dt>
                    <dd class="font-medium">
                        {{ $snapshot->birthDate->format('Y-m-d') }}
                        @if ($snapshot->ageYears() !== null)
                            <span class="text-xs text-gray-500">
                                ({{ __('owner/horse_detail.field.age', ['years' => $snapshot->ageYears()]) }})
                            </span>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($snapshot->passportNumber)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.passport_number') }}</dt>
                    <dd class="font-mono text-sm">{{ $snapshot->passportNumber }}</dd>
                </div>
            @endif
            @if ($snapshot->microchip)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.microchip') }}</dt>
                    <dd class="font-mono text-sm">{{ $snapshot->microchip }}</dd>
                </div>
            @endif
            @if ($snapshot->ueln)
                <div>
                    <dt class="text-xs uppercase text-gray-500">{{ __('owner/horse_detail.field.ueln') }}</dt>
                    <dd class="font-mono text-sm">{{ $snapshot->ueln }}</dd>
                </div>
            @endif
        </dl>
    </section>

    {{-- Sekcja: aktualny boks --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold">{{ __('owner/horse_detail.section.current_box') }}</h2>
        @if ($snapshot->currentBox)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/40">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold">{{ $snapshot->currentBox->boxName }}</div>
                        @if ($snapshot->currentBox->buildingName)
                            <div class="text-sm text-gray-500">{{ $snapshot->currentBox->buildingName }}</div>
                        @endif
                    </div>
                    @if ($snapshot->currentBox->monthlyRateCents)
                        <div class="text-right">
                            <div class="text-xs uppercase text-gray-500">
                                {{ __('owner/horse_detail.field.monthly_rate') }}
                            </div>
                            <div class="font-medium">
                                {{ $this->formatCents($snapshot->currentBox->monthlyRateCents) }}
                            </div>
                        </div>
                    @endif
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    {{ __('owner/horse_detail.field.assigned_at', ['date' => $snapshot->currentBox->assignedAt->format('Y-m-d')]) }}
                </div>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800">
                {{ __('owner/horse_detail.empty.no_box') }}
            </div>
        @endif
    </section>

    {{-- Sekcja: aktywne boarding services --}}
    <section class="space-y-3">
        <h2 class="text-base font-semibold">{{ __('owner/horse_detail.section.boarding_services') }}</h2>
        @if (count($snapshot->boardingServices) > 0)
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-2">{{ __('owner/horse_detail.table.service_name') }}</th>
                            <th class="px-4 py-2">{{ __('owner/horse_detail.table.frequency') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('owner/horse_detail.table.price') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($snapshot->boardingServices as $service)
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $service->name }}</div>
                                    @if ($service->description)
                                        <div class="text-xs text-gray-500">{{ $service->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    {{ __('owner/horse_detail.frequency.'.$service->frequency) }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="font-medium">
                                        {{ $this->formatCents($service->effectivePriceCents, $service->currency) }}
                                    </div>
                                    @if ($service->quantity != 1)
                                        <div class="text-xs text-gray-500">
                                            × {{ number_format($service->quantity, 2, ',', ' ') }} {{ $service->unit }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500 dark:border-gray-800">
                {{ __('owner/horse_detail.empty.no_services') }}
            </div>
        @endif

        @if ($snapshot->estimatedMonthlyCostCents !== null && $snapshot->estimatedMonthlyCostCents > 0)
            <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">{{ __('owner/horse_detail.field.estimated_monthly_cost') }}</div>
                    <div class="text-lg font-bold text-primary-700 dark:text-primary-300">
                        {{ $this->formatCents($snapshot->estimatedMonthlyCostCents) }}
                    </div>
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ __('owner/horse_detail.field.estimated_monthly_cost_hint') }}
                </div>
            </div>
        @endif
    </section>

    {{-- Sekcja: notatki stajni (jeśli są) --}}
    @if ($snapshot->notes)
        <section class="space-y-3">
            <h2 class="text-base font-semibold">{{ __('owner/horse_detail.section.notes') }}</h2>
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900/40">
                {{ $snapshot->notes }}
            </div>
        </section>
    @endif

    {{-- Linki do sub-sekcji — wszystkie 5 z Faz 2-5 gotowe. --}}
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <a
            href="{{ \App\Filament\Owner\Pages\HorseTimeline::getUrl(['centralHorseId' => $snapshot->centralHorseId]) }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/horse_timeline.title') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/horse_detail.upcoming.timeline') }}</div>
            </div>
        </a>
        <a
            href="{{ \App\Filament\Owner\Pages\HorseCare::getUrl(['centralHorseId' => $snapshot->centralHorseId]) }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-scale" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/horse_care.page.title') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/horse_care.feeding.note') }}</div>
            </div>
        </a>
        <a
            href="{{ \App\Filament\Owner\Pages\HorseMessages::getUrl(['centralHorseId' => $snapshot->centralHorseId]) }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/messages.page.title') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/horse_detail.upcoming.messages') }}</div>
            </div>
        </a>
        <a
            href="{{ \App\Filament\Owner\Pages\HorseGallery::getUrl(['centralHorseId' => $snapshot->centralHorseId]) }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-photo" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/photos.page.title') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/horse_detail.upcoming.files') }}</div>
            </div>
        </a>
        <a
            href="{{ \App\Filament\Owner\Pages\HorseDocuments::getUrl(['centralHorseId' => $snapshot->centralHorseId]) }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-folder" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/documents.page.title') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/documents.page.empty_description') }}</div>
            </div>
        </a>
        <a
            href="{{ \App\Filament\Owner\Pages\InvoiceList::getUrl().'?horse='.$snapshot->centralHorseId }}"
            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900/40 dark:hover:bg-primary-900/20"
        >
            <x-filament::icon icon="heroicon-o-document-text" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            <div>
                <div class="font-medium">{{ __('owner/invoices.navigation') }}</div>
                <div class="text-xs text-gray-500">{{ __('owner/horse_detail.upcoming.invoices') }}</div>
            </div>
        </a>
    </section>
</x-filament-panels::page>
