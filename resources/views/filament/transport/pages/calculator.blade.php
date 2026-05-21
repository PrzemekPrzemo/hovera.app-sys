<x-filament-panels::page>
    <div
        x-data="calculatorLivePreview({
            previewUrl: @js(route('api.transport.calculator.preview')),
            currency: @js(__('transport/calculator.live.currency_fallback')),
            labels: {
                title: @js(__('transport/calculator.live.title')),
                hint: @js(__('transport/calculator.live.hint')),
                loading: @js(__('transport/calculator.live.loading')),
                missing: @js(__('transport/calculator.live.missing')),
                error: @js(__('transport/calculator.live.error')),
                distance: @js(__('transport/calculator.result.distance')),
                base: @js(__('transport/calculator.result.base_cost')),
                fuel: @js(__('transport/calculator.result.fuel_surcharge')),
                netTotal: @js(__('transport/calculator.result.net_total')),
                grossTotal: @js(__('transport/calculator.result.gross_total')),
            },
        })"
        x-init="init()"
    >
        <form wire:submit="calculate" class="space-y-6">
            {{ $this->form }}

            <div class="flex items-center justify-end gap-3">
                <span
                    class="text-xs text-gray-500"
                    x-show="status !== 'idle'"
                    x-text="statusLabel()"
                ></span>
                <x-filament::button type="submit">
                    {{ __('transport/calculator.action.submit') }}
                </x-filament::button>
            </div>
        </form>

        {{-- Live podgląd ceny — fetchowany przez Alpine z debounce 500ms.
             Pokazuje się tylko gdy mamy preview data; pełna canonical
             wycena (server-rendered) jest poniżej po submit'cie formy. --}}
        <template x-if="preview !== null">
            <div class="mt-6 rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20">
                <div class="mb-2 flex items-center justify-between">
                    <div class="text-sm font-semibold text-primary-700 dark:text-primary-300" x-text="labels.title"></div>
                    <div class="text-xs text-gray-500" x-text="labels.hint"></div>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase text-gray-500" x-text="labels.distance"></dt>
                        <dd class="font-medium" x-text="formatDistance(preview.distance_km)"></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500" x-text="labels.base"></dt>
                        <dd class="font-medium" x-text="formatMoney(preview.base_cost, preview.currency)"></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500" x-text="labels.netTotal"></dt>
                        <dd class="font-medium" x-text="formatMoney(preview.net_total, preview.currency)"></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-500" x-text="labels.grossTotal"></dt>
                        <dd class="text-lg font-bold text-primary-700 dark:text-primary-300" x-text="formatMoney(preview.gross_total, preview.currency)"></dd>
                    </div>
                </dl>
            </div>
        </template>

        <template x-if="error !== null">
            <div class="mt-4 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-300">
                <span x-text="error"></span>
            </div>
        </template>
    </div>

    @if ($quotation)
        <div class="mt-8 space-y-4">
            <h2 class="text-xl font-bold">{{ __('transport/calculator.result.heading') }}</h2>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">{{ __('transport/calculator.result.from') }}</div>
                    <div class="font-medium">{{ $fromDisplayName }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                    <div class="text-xs uppercase text-gray-500">{{ __('transport/calculator.result.to') }}</div>
                    <div class="font-medium">{{ $toDisplayName }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.distance') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($quotation->distanceKm, 2, ',', ' ') }} km</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.duration') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ floor($quotation->durationSeconds / 3600) }}h {{ floor(($quotation->durationSeconds % 3600) / 60) }}min</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.rate_used') }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($quotation->rateUsed, 2, ',', ' ') }} {{ $quotation->currency }}/km</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.base_cost') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->baseCost, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.fuel_surcharge') }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->fuelSurcharge, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        @if ($quotation->extraHorseFeeTotal > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.extra_horse_fee', ['count' => $quotation->horsesCount - 1, 'rate' => number_format($quotation->extraHorseFeePerHead, 2, ',', ' '), 'currency' => $quotation->currency]) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->extraHorseFeeTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        @foreach ($quotation->fixedFees as $fee)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ $fee['name'] }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) $fee['amount'], 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endforeach
                        @if ($quotation->minimumAdjustment > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.minimum_adjustment') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->minimumAdjustment, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        @if ($quotation->surchargeAmount > 0)
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.surcharge', ['percent' => rtrim(rtrim(number_format($quotation->surchargePercent, 2, ',', ' '), '0'), ',')]) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($quotation->surchargeAmount, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                            </tr>
                        @endif
                        <tr class="bg-gray-50 dark:bg-gray-900">
                            <td class="px-4 py-2 font-semibold">{{ __('transport/calculator.result.net_total') }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($quotation->netTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 text-gray-500">{{ __('transport/calculator.result.vat', ['rate' => number_format($quotation->vatRate, 0)]) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($quotation->vatAmount, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                        <tr class="bg-primary-50 dark:bg-primary-900/30">
                            <td class="px-4 py-3 text-lg font-bold">{{ __('transport/calculator.result.gross_total') }}</td>
                            <td class="px-4 py-3 text-right text-lg font-bold">{{ number_format($quotation->grossTotal, 2, ',', ' ') }} {{ $quotation->currency }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Mapa Leaflet z trasą (decode polyline'a + markery start/end).
                 Patrz docs/MARKETPLACE-ROADMAP.md "Calculator live UX (Leaflet)". --}}
            <x-route-map
                :polyline="$quotation->polyline"
                :fromLat="$pendingPickupLat"
                :fromLng="$pendingPickupLng"
                :toLat="$pendingDropoffLat"
                :toLng="$pendingDropoffLng"
                height="360px"
            />

            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    {{ __('transport/calculator.result.routing_via', ['provider' => $quotation->routingProvider]) }}
                </div>
                <x-filament::button wire:click="saveAsQuote" icon="heroicon-o-document-plus">
                    {{ __('transport/calculator.action.save_as_quote') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            // Live preview kalkulatora — Alpine component zarejestrowany
            // globalnie, używany przez x-data w blade'cie powyżej. Bije się
            // z `/api/transport/calculator/preview` debounce'em 500ms po
            // każdej zmianie pól formy.
            //
            // Subskrybujemy `data` z Livewire poprzez Livewire.hook
            // `commit` (Livewire 3) — każda zmiana state'u (wire:model,
            // toggle, repeater) triggeruje hook, a debounce trzyma ruch
            // do API w okolicach 1 req / 500ms / typ.
            //
            // Patrz docs/MARKETPLACE-ROADMAP.md "Calculator live UX".
            window.calculatorLivePreview = function (cfg) {
                return {
                    previewUrl: cfg.previewUrl,
                    currency: cfg.currency,
                    labels: cfg.labels,
                    preview: null,
                    status: 'idle', // idle | loading | ok | missing | error
                    error: null,
                    _timer: null,
                    _abort: null,
                    _lastPayload: null,

                    init() {
                        const trigger = () => this.scheduleFetch();

                        // Livewire 3: commit hook odpala się po każdej
                        // serializacji state'u (np. zmiana wire:model). Bez
                        // tego nie wiedzielibyśmy o zmianach repeater'a /
                        // toggle'a — tylko o input event'ach.
                        if (window.Livewire && window.Livewire.hook) {
                            window.Livewire.hook('commit', ({ component }) => {
                                if (component?.el?.contains?.(this.$root)) {
                                    trigger();
                                }
                            });
                        }

                        // Pierwsze sprawdzenie — jeśli form jest pre-fillowany
                        // (np. z lead'a), od razu pokaż preview.
                        this.scheduleFetch(50);
                    },

                    scheduleFetch(delay = 500) {
                        clearTimeout(this._timer);
                        this._timer = setTimeout(() => this.fetchPreview(), delay);
                    },

                    payload() {
                        // Odczytujemy state z Livewire'a (component.data).
                        // Livewire 3 nie ma `data()` getter — używamy
                        // `$wire` dostarczanego przez Filament.
                        const wire = this.$root.closest('[wire\\:id]');
                        const id = wire?.getAttribute('wire:id');
                        const component = id && window.Livewire?.find(id);
                        const data = component?.data?.data ?? {};

                        return {
                            from_address: data.from_address ?? null,
                            to_address: data.to_address ?? null,
                            calculation_mode: data.mode ?? 'one_way',
                            loaded: !!data.loaded,
                            horses_count: parseInt(data.horses_count ?? 1, 10) || 1,
                            fixed_fees: Array.isArray(data.fixed_fees)
                                ? data.fixed_fees.map((f) => ({
                                    name: f?.name ?? '',
                                    amount: parseFloat(f?.amount ?? 0) || 0,
                                }))
                                : [],
                            surcharge_percent: data.surcharge_percent === '' || data.surcharge_percent === null
                                ? null
                                : parseFloat(data.surcharge_percent),
                            avoid_tolls: !!data.avoid_tolls,
                            avoid_ferries: !!data.avoid_ferries,
                            profile: data.profile ?? 'truck',
                        };
                    },

                    async fetchPreview() {
                        const body = this.payload();
                        if (!body.from_address || !body.to_address) {
                            this.status = 'missing';
                            this.preview = null;
                            this.error = null;

                            return;
                        }

                        // Deduplikacja — jeśli payload się nie zmienił, nie
                        // bijemy API ponownie (Livewire commit hook potrafi
                        // odpalać też przy zmianach nie-formowych).
                        const serialised = JSON.stringify(body);
                        if (serialised === this._lastPayload) {
                            return;
                        }
                        this._lastPayload = serialised;

                        // Abort poprzedniego request'u jeśli wciąż w locie —
                        // dwa równoległe response'y wpadające w odwrotnej
                        // kolejności pokazałyby zły stan.
                        this._abort?.abort();
                        this._abort = new AbortController();

                        this.status = 'loading';
                        this.error = null;

                        try {
                            const response = await fetch(this.previewUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                                signal: this._abort.signal,
                                body: serialised,
                            });

                            if (!response.ok) {
                                const data = await response.json().catch(() => ({}));
                                this.status = 'error';
                                this.error = data.error ?? this.labels.error;
                                this.preview = null;

                                return;
                            }

                            const data = await response.json();
                            this.status = 'ok';
                            this.preview = data.quotation ?? null;
                            this.error = null;
                        } catch (e) {
                            if (e.name === 'AbortError') {
                                return;
                            }
                            this.status = 'error';
                            this.error = this.labels.error;
                            this.preview = null;
                        }
                    },

                    statusLabel() {
                        if (this.status === 'loading') return this.labels.loading;
                        if (this.status === 'missing') return this.labels.missing;

                        return '';
                    },

                    formatMoney(value, currency) {
                        const n = parseFloat(value ?? 0) || 0;
                        const formatted = n.toLocaleString('pl-PL', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });

                        return `${formatted} ${currency ?? this.currency}`;
                    },

                    formatDistance(value) {
                        const n = parseFloat(value ?? 0) || 0;

                        return `${n.toLocaleString('pl-PL', { maximumFractionDigits: 2 })} km`;
                    },
                };
            };
        </script>
    @endpush
</x-filament-panels::page>
