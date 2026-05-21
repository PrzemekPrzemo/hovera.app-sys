<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('owner/stable_marketplace.intro') }}
        </p>

        @php($stables = $this->stables())

        @if ($stables->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
                <div class="text-base font-semibold">{{ __('owner/stable_marketplace.empty.heading') }}</div>
                <div class="mt-2 text-sm text-gray-500">{{ __('owner/stable_marketplace.empty.description') }}</div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($stables as $stable)
                    <div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/40">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                                <x-filament::icon icon="heroicon-o-building-office-2" class="h-5 w-5 text-primary-700 dark:text-primary-300" />
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold">{{ $stable->name }}</div>
                                <div class="text-xs font-mono text-gray-500">{{ $stable->slug }}</div>
                                @if ($stable->country)
                                    <div class="mt-1 text-xs text-gray-500">{{ $stable->country }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4">
                            {{ $this->requestBoardingAction()(['stable_id' => $stable->id, 'stable_name' => $stable->name]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <x-filament-actions::modals />
    </div>
</x-filament-panels::page>
