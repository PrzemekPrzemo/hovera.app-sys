<x-filament-panels::page>
    {{-- Hero z nazwą stajni + read-only banner gdy ended boarding --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/20">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                    {{ __('owner/photos.page.stable') }}
                </div>
                <div class="font-semibold">{{ $this->stableTenant->name }}</div>
            </div>
            @if (! $this->canUpload)
                <div class="text-xs text-amber-700 dark:text-amber-300">
                    {{ __('owner/photos.access.upload_requires_active_boarding') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Upload form (disabled gdy ended) --}}
    <form wire:submit="upload" class="space-y-3">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit" :disabled="! $this->canUpload" icon="heroicon-o-arrow-up-tray">
                {{ __('owner/photos.form.upload_button') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Galeria --}}
    @if (empty($this->photos))
        <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
            <div class="text-base font-semibold">{{ __('owner/photos.page.empty_heading') }}</div>
            <div class="mt-2 text-sm text-gray-500">{{ __('owner/photos.page.empty_description') }}</div>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->photos as $photo)
                @php($downloadUrl = $this->downloadUrl($photo))
                <div class="group relative overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900/40">
                    {{-- Thumb (klick → full size w nowej karcie) --}}
                    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="block aspect-square">
                        <img
                            src="{{ $downloadUrl }}"
                            alt="{{ $photo->caption ?? $photo->originalName }}"
                            class="h-full w-full object-cover transition-transform group-hover:scale-105"
                            loading="lazy"
                        />
                    </a>
                    {{-- Overlay z metadata --}}
                    <div class="space-y-1 p-2">
                        <div class="flex items-center justify-between gap-2">
                            <span
                                @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' => $photo->uploadedByRole === 'client',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $photo->uploadedByRole !== 'client',
                                ])
                            >
                                {{ $photo->uploadedByRole === 'client' ? __('owner/photos.uploader.you') : __('owner/photos.uploader.stable') }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $photo->createdAt->format('Y-m-d') }}</span>
                        </div>
                        @if ($photo->caption)
                            <div class="line-clamp-2 text-xs text-gray-700 dark:text-gray-300">{{ $photo->caption }}</div>
                        @endif
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] text-gray-400">{{ $this->formatFileSize($photo->sizeBytes) }}</span>
                            @if ($this->canDelete($photo))
                                <button
                                    wire:click="deletePhoto({{ \Illuminate\Support\Js::from($photo->id) }})"
                                    wire:confirm="{{ __('owner/photos.form.delete_confirm') }}"
                                    type="button"
                                    class="text-xs text-rose-600 hover:underline dark:text-rose-400"
                                >
                                    {{ __('owner/photos.form.delete') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
