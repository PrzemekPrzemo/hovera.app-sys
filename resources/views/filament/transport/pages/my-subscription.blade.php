@php
    $data = $this->getViewData();
    $tenant = $data['tenant'];
    $currentPlan = $data['currentPlan'];
    $availablePlans = $data['availablePlans'];
    $usage = $data['usage'];
    $addonPurchases = $data['addonPurchases'];
@endphp

<x-filament-panels::page>
    {{-- Current plan summary --}}
    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="text-xs uppercase tracking-wide font-bold text-gray-500 dark:text-gray-400 mb-1">
                    {{ __('transport/subscription.current_plan_label') }}
                </div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ $currentPlan?->name ?: __('transport/subscription.no_plan') }}
                </h2>
                @if ($currentPlan)
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $currentPlan->price_monthly_cents > 0
                            ? number_format($currentPlan->price_monthly_cents / 100, 0, ',', ' ').' '.($currentPlan->currency ?? 'PLN').' / '.__('transport/subscription.per_month')
                            : __('transport/subscription.contact_sales') }}
                    </div>
                @endif
            </div>
            <div class="text-right">
                @if ($tenant?->status === 'trialing')
                    <span class="inline-block px-3 py-1 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-bold rounded">
                        {{ __('transport/subscription.status.trialing') }}
                    </span>
                    @if ($tenant->trial_ends_at)
                        <div class="text-xs text-gray-500 mt-1">
                            {{ __('transport/subscription.trial_ends_at', ['date' => $tenant->trial_ends_at->toDateString()]) }}
                        </div>
                    @endif
                @elseif ($tenant?->status === 'active')
                    <span class="inline-block px-3 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded">
                        {{ __('transport/subscription.status.active') }}
                    </span>
                    @if ($tenant->current_period_ends_at)
                        <div class="text-xs text-gray-500 mt-1">
                            {{ __('transport/subscription.next_renewal', ['date' => $tenant->current_period_ends_at->toDateString()]) }}
                        </div>
                    @endif
                @elseif ($tenant?->status === 'provisioning')
                    <span class="inline-block px-3 py-1 bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-xs font-bold rounded">
                        {{ __('transport/subscription.status.provisioning') }}
                    </span>
                @elseif ($tenant?->status === 'past_due')
                    <span class="inline-block px-3 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 text-xs font-bold rounded">
                        {{ __('transport/subscription.status.past_due') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Usage panel --}}
    <div class="mt-5 p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">
            {{ __('transport/subscription.usage.heading') }}
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Vehicles --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('transport/subscription.usage.vehicles') }}
                    </span>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">
                        {{ $usage['vehicles']['used'] }} /
                        @if ($usage['vehicles']['limit'] === null)
                            ∞
                        @else
                            {{ $usage['vehicles']['limit'] }}
                        @endif
                    </span>
                </div>
                @if ($usage['vehicles']['percent'] !== null)
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $usage['vehicles']['near_limit'] ? 'bg-amber-500' : 'bg-emerald-500' }}"
                             style="width: {{ $usage['vehicles']['percent'] }}%"></div>
                    </div>
                @endif
            </div>

            {{-- Drivers --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('transport/subscription.usage.drivers') }}
                    </span>
                    <span class="text-sm font-bold text-gray-900 dark:text-white">
                        {{ $usage['drivers']['used'] }} /
                        @if ($usage['drivers']['limit'] === null)
                            ∞
                        @else
                            {{ $usage['drivers']['limit'] }}
                        @endif
                    </span>
                </div>
                @if ($usage['drivers']['percent'] !== null)
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $usage['drivers']['near_limit'] ? 'bg-amber-500' : 'bg-emerald-500' }}"
                             style="width: {{ $usage['drivers']['percent'] }}%"></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Available plans grid --}}
    <div class="mt-5">
        <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">
            {{ __('transport/subscription.plans.heading') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($availablePlans as $plan)
                @php($isCurrent = $currentPlan && $plan->id === $currentPlan->id)
                <div class="p-5 rounded-xl border-2 {{ $isCurrent ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }}">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h4>
                        @if ($isCurrent)
                            <span class="text-[10px] uppercase font-bold text-primary-700 dark:text-primary-300 bg-primary-100 dark:bg-primary-900/50 px-2 py-0.5 rounded">
                                {{ __('transport/subscription.plans.current') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                        @if ($plan->price_monthly_cents > 0)
                            {{ number_format($plan->price_monthly_cents / 100, 0, ',', ' ') }} {{ $plan->currency ?? 'PLN' }}
                            <span class="text-sm font-normal text-gray-500">/{{ __('transport/subscription.per_month_short') }}</span>
                        @else
                            <span class="text-base font-medium text-gray-600 dark:text-gray-300">{{ __('transport/subscription.contact_sales') }}</span>
                        @endif
                    </div>
                    @php($limits = $plan->limits ?? [])
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1 mb-4">
                        <li>
                            <span class="font-medium">{{ __('transport/subscription.usage.vehicles') }}:</span>
                            {{ ($limits['max_vehicles'] ?? 0) < 0 ? '∞' : ($limits['max_vehicles'] ?? '—') }}
                        </li>
                        <li>
                            <span class="font-medium">{{ __('transport/subscription.usage.drivers') }}:</span>
                            {{ ($limits['max_drivers'] ?? 0) < 0 ? '∞' : ($limits['max_drivers'] ?? '—') }}
                        </li>
                    </ul>

                    @if (! $isCurrent)
                        @if ($plan->price_monthly_cents > 0)
                            <form method="post" action="{{ url('/app/billing/checkout') }}">
                                @csrf
                                <input type="hidden" name="plan_code" value="{{ $plan->code }}">
                                <input type="hidden" name="period" value="monthly">
                                <button type="submit"
                                        class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg text-sm transition-colors">
                                    {{ __('transport/subscription.plans.choose') }}
                                </button>
                            </form>
                        @else
                            <a href="mailto:{{ config('hovera.legal.contact_email', 'office@hovera.app') }}?subject={{ urlencode(__('transport/subscription.plans.enterprise_inquiry')) }}"
                               class="block w-full text-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-bold rounded-lg text-sm hover:border-primary-500">
                                {{ __('transport/subscription.plans.contact_us') }}
                            </a>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Addons history --}}
    @if ($addonPurchases->isNotEmpty())
        <div class="mt-5 p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">
                {{ __('transport/subscription.addons.heading') }}
            </h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-3">{{ __('transport/subscription.addons.code') }}</th>
                        <th class="py-2 pr-3">{{ __('transport/subscription.addons.price') }}</th>
                        <th class="py-2 pr-3">{{ __('transport/subscription.addons.status') }}</th>
                        <th class="py-2 pr-3">{{ __('transport/subscription.addons.purchased_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($addonPurchases as $purchase)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-3 text-gray-900 dark:text-white font-medium">{{ $purchase->addon_code }}</td>
                            <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">
                                {{ number_format(($purchase->price_cents ?? 0) / 100, 2, ',', ' ') }} {{ $purchase->currency ?? 'PLN' }}
                            </td>
                            <td class="py-2 pr-3">
                                <span class="text-xs px-2 py-0.5 rounded font-medium {{ $purchase->status === 'paid' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' }}">
                                    {{ $purchase->status }}
                                </span>
                            </td>
                            <td class="py-2 pr-3 text-gray-500 dark:text-gray-400 text-xs">
                                {{ optional($purchase->created_at)->toDateTimeString() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                {{ __('transport/subscription.addons.contact_hint') }}
            </p>
        </div>
    @else
        <div class="mt-5 p-5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
            <h3 class="text-base font-bold text-amber-900 dark:text-amber-100 mb-1">
                {{ __('transport/subscription.addons.empty_heading') }}
            </h3>
            <p class="text-sm text-amber-800 dark:text-amber-200">
                {{ __('transport/subscription.addons.empty_body') }}
            </p>
        </div>
    @endif
</x-filament-panels::page>
