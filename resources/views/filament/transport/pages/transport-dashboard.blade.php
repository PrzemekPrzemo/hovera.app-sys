@php
    $counts = $this->getHeroCounts();
    $checklist = $this->getOnboardingChecklist();
    $isChecklistComplete = $checklist['completed_count'] === $checklist['total_count'];

    /**
     * Hero CTA cards definition — pojedyncze źródło prawdy dla całego gridu.
     * Każda karta = jeden cel biznesowy. „primary" oznacza CTA pierwsze
     * — wyróżnione subtelnym gradientem zamiast headline'owym borderem.
     */
    $heroCards = [
        [
            'title' => __('transport/dashboard.hero.calculator.title'),
            'body' => __('transport/dashboard.hero.calculator.body'),
            'href' => route('filament.transport.pages.calculator'),
            'icon' => 'heroicon-o-calculator',
            'tone' => 'primary',
            'badge' => __('transport/dashboard.hero.primary_badge'),
            'count' => null,
        ],
        [
            'title' => __('transport/dashboard.hero.leads.title'),
            'body' => $counts['unseen_leads'] > 0
                ? trans_choice('transport/dashboard.hero.leads.body_with_count', $counts['unseen_leads'], ['count' => $counts['unseen_leads']])
                : __('transport/dashboard.hero.leads.body_empty'),
            'href' => route('filament.transport.resources.leads.index'),
            'icon' => 'heroicon-o-inbox-arrow-down',
            'tone' => 'info',
            'badge' => null,
            'count' => $counts['unseen_leads'] ?: null,
        ],
        [
            'title' => __('transport/dashboard.hero.quotes.title'),
            'body' => $counts['pending_quotes'] > 0
                ? trans_choice('transport/dashboard.hero.quotes.body_with_count', $counts['pending_quotes'], ['count' => $counts['pending_quotes']])
                : __('transport/dashboard.hero.quotes.body_empty'),
            'href' => route('filament.transport.resources.quotes.index'),
            'icon' => 'heroicon-o-document-text',
            'tone' => 'warning',
            'badge' => null,
            'count' => $counts['pending_quotes'] ?: null,
        ],
        [
            'title' => __('transport/dashboard.hero.invoices.title'),
            'body' => $counts['unpaid_invoices'] > 0
                ? __('transport/dashboard.hero.invoices.body_with_amount', [
                    'amount' => number_format($counts['unpaid_invoices_cents'] / 100, 0, ',', ' ').' zł',
                ])
                : __('transport/dashboard.hero.invoices.body_empty'),
            'href' => route('filament.transport.resources.transport-invoices.index'),
            'icon' => 'heroicon-o-banknotes',
            'tone' => 'success',
            'badge' => null,
            'count' => $counts['unpaid_invoices'] ?: null,
        ],
    ];

    /**
     * Tailwind color tokens per `tone` — wspólne dla icon background,
     * border accent i badge. Trzymamy mapowanie tu (centralnie) zamiast
     * w klasach na CTA — łatwiej dodać nowy „tone" przyszłościowo.
     */
    $toneClasses = [
        'primary' => [
            'icon_bg' => 'bg-primary-50 dark:bg-primary-950/40',
            'icon_text' => 'text-primary-600 dark:text-primary-400',
            'badge_bg' => 'bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300',
            'gradient' => 'bg-gradient-to-br from-primary-50/50 via-white to-white dark:from-primary-950/20 dark:via-gray-900 dark:to-gray-900',
            'border_hover' => 'hover:border-primary-300 dark:hover:border-primary-700',
            'count_bg' => 'bg-primary-600',
        ],
        'info' => [
            'icon_bg' => 'bg-blue-50 dark:bg-blue-950/40',
            'icon_text' => 'text-blue-600 dark:text-blue-400',
            'badge_bg' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300',
            'gradient' => 'bg-white dark:bg-gray-900',
            'border_hover' => 'hover:border-blue-300 dark:hover:border-blue-700',
            'count_bg' => 'bg-blue-600',
        ],
        'warning' => [
            'icon_bg' => 'bg-amber-50 dark:bg-amber-950/40',
            'icon_text' => 'text-amber-600 dark:text-amber-400',
            'badge_bg' => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
            'gradient' => 'bg-white dark:bg-gray-900',
            'border_hover' => 'hover:border-amber-300 dark:hover:border-amber-700',
            'count_bg' => 'bg-amber-600',
        ],
        'success' => [
            'icon_bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
            'icon_text' => 'text-emerald-600 dark:text-emerald-400',
            'badge_bg' => 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
            'gradient' => 'bg-white dark:bg-gray-900',
            'border_hover' => 'hover:border-emerald-300 dark:hover:border-emerald-700',
            'count_bg' => 'bg-emerald-600',
        ],
    ];
@endphp

<x-filament-panels::page>
    {{-- Hero CTA grid — 4 karty z modular tone'em (primary / info / warning / success).
         Subtelny gradient + soft shadow + smooth hover transition. Mobile-first
         responsive: 1 col → 2 col @sm → 4 col @xl. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($heroCards as $card)
            @php($tone = $toneClasses[$card['tone']])
            <a href="{{ $card['href'] }}"
               class="group relative flex flex-col gap-3 p-5 rounded-xl border border-gray-200 dark:border-gray-800 {{ $tone['gradient'] }} {{ $tone['border_hover'] }} shadow-sm hover:shadow-md transition-all duration-200"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-lg {{ $tone['icon_bg'] }}">
                        <x-filament::icon :icon="$card['icon']" class="w-6 h-6 {{ $tone['icon_text'] }}" />
                    </div>
                    @if ($card['badge'])
                        <span class="text-[10px] uppercase tracking-wider font-bold {{ $tone['badge_bg'] }} px-2 py-1 rounded-md">
                            {{ $card['badge'] }}
                        </span>
                    @endif
                    @if ($card['count'])
                        <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 text-xs font-bold text-white {{ $tone['count_bg'] }} rounded-full shadow-sm">
                            {{ $card['count'] }}
                        </span>
                    @endif
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1 group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors">
                        {{ $card['title'] }}
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                        {{ $card['body'] }}
                    </p>
                </div>
                <div class="mt-auto flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                    <span>{{ __('transport/dashboard.hero.cta_open') }}</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="w-3 h-3 transition-transform group-hover:translate-x-0.5" />
                </div>
            </a>
        @endforeach
    </div>

    {{-- Onboarding checklist — tylko gdy niekompletna. Refined design:
         subtelny gradient, progress bar, ikony state'u, cleaner spacing. --}}
    @unless ($isChecklistComplete)
        @php
            $checklistSteps = [
                [
                    'href' => route('filament.transport.pages.transporter-documents'),
                    'done' => $checklist['verified'],
                    'icon_todo' => 'heroicon-o-document-check',
                    'label_todo' => __('transport/dashboard.onboarding.step.verify'),
                    'label_done' => __('transport/dashboard.onboarding.step.verified'),
                ],
                [
                    'href' => route('filament.transport.resources.vehicles.index'),
                    'done' => $checklist['has_vehicles'],
                    'icon_todo' => 'heroicon-o-truck',
                    'label_todo' => __('transport/dashboard.onboarding.step.add_vehicle'),
                    'label_done' => __('transport/dashboard.onboarding.step.vehicles_done'),
                ],
                [
                    'href' => route('filament.transport.resources.drivers.index'),
                    'done' => $checklist['has_drivers'],
                    'icon_todo' => 'heroicon-o-user-plus',
                    'label_todo' => __('transport/dashboard.onboarding.step.add_driver'),
                    'label_done' => __('transport/dashboard.onboarding.step.drivers_done'),
                ],
                [
                    'href' => route('filament.transport.pages.service-areas'),
                    'done' => $checklist['has_service_areas'],
                    'icon_todo' => 'heroicon-o-map',
                    'label_todo' => __('transport/dashboard.onboarding.step.set_service_areas'),
                    'label_done' => __('transport/dashboard.onboarding.step.service_areas_done'),
                ],
            ];
            $progressPercent = (int) round($checklist['completed_count'] / max(1, $checklist['total_count']) * 100);
        @endphp
        <div class="mt-6 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-gradient-to-br from-amber-50 to-orange-50/30 dark:from-amber-950/30 dark:to-orange-950/10 p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50">
                        <x-filament::icon icon="heroicon-o-rocket-launch" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-amber-900 dark:text-amber-100">
                            {{ __('transport/dashboard.onboarding.heading') }}
                        </h3>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                            {{ __('transport/dashboard.onboarding.intro') }}
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-amber-900 dark:text-amber-100 leading-none">
                        {{ $checklist['completed_count'] }}<span class="text-base text-amber-600 dark:text-amber-400">/{{ $checklist['total_count'] }}</span>
                    </div>
                </div>
            </div>

            <div class="w-full h-2 bg-amber-100 dark:bg-amber-950/60 rounded-full overflow-hidden mb-4">
                <div class="h-full bg-amber-500 dark:bg-amber-400 rounded-full transition-all duration-500"
                     style="width: {{ $progressPercent }}%"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($checklistSteps as $step)
                    <a href="{{ $step['href'] }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/80 dark:bg-gray-900/40 hover:bg-white dark:hover:bg-gray-900 border border-amber-200/50 dark:border-amber-900/40 hover:border-amber-400 dark:hover:border-amber-700 transition-all group"
                    >
                        @if ($step['done'])
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white shrink-0">
                                <x-filament::icon icon="heroicon-s-check" class="w-4 h-4" />
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400 line-through">{{ $step['label_done'] }}</span>
                        @else
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/40 shrink-0">
                                <x-filament::icon :icon="$step['icon_todo']" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            </span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white flex-1">{{ $step['label_todo'] }}</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="w-4 h-4 text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors shrink-0" />
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endunless

    {{-- KPI/finance widgety. Filament 3 renderuje je przez `getVisibleWidgets()`
         z Dashboard base class. Nasz custom view dorzuca dodatkową przerwę
         + sekcyjny heading dla lepszej hierarchii wizualnej. --}}
    <div class="mt-8">
        <div class="flex items-center gap-2 mb-4">
            <x-filament::icon icon="heroicon-o-chart-bar" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ __('transport/dashboard.widgets_section') }}
            </h2>
        </div>
        <x-filament-widgets::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
            :data="['filters' => $this->filters ?? null]"
        />
    </div>
</x-filament-panels::page>
