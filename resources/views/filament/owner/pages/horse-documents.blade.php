<x-filament-panels::page>
    {{-- Hero --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/20">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                    {{ __('owner/documents.page.stable') }}
                </div>
                <div class="font-semibold">{{ $this->stableTenant->name }}</div>
            </div>
            @if (! $this->canUpload)
                <div class="text-xs text-amber-700 dark:text-amber-300">
                    {{ __('owner/documents.access.upload_requires_active_boarding') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Upload form --}}
    <form wire:submit="upload" class="space-y-3">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit" :disabled="! $this->canUpload" icon="heroicon-o-arrow-up-tray">
                {{ __('owner/documents.form.upload_button') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Lista dokumentów --}}
    @if (empty($this->documents))
        <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
            <div class="text-base font-semibold">{{ __('owner/documents.page.empty_heading') }}</div>
            <div class="mt-2 text-sm text-gray-500">{{ __('owner/documents.page.empty_description') }}</div>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-2">{{ __('owner/documents.table.name') }}</th>
                        <th class="px-4 py-2">{{ __('owner/documents.table.kind') }}</th>
                        <th class="px-4 py-2">{{ __('owner/documents.table.valid_until') }}</th>
                        <th class="px-4 py-2">{{ __('owner/documents.table.uploaded_by') }}</th>
                        <th class="px-4 py-2">{{ __('owner/documents.table.added') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('owner/documents.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->documents as $doc)
                        <tr>
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ $doc->name }}</div>
                                @if ($doc->description)
                                    <div class="text-xs text-gray-500">{{ $doc->description }}</div>
                                @endif
                                <div class="text-[10px] text-gray-400">{{ $doc->originalName }} · {{ $this->formatFileSize($doc->sizeBytes) }}</div>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $this->kindLabel($doc->kind) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($doc->validUntil)
                                    <div class="text-xs">{{ $doc->validUntil->format('Y-m-d') }}</div>
                                    @if ($doc->isExpired())
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                            {{ __('owner/documents.page.expired_badge') }}
                                        </span>
                                    @elseif ($doc->isExpiringSoon())
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                            {{ __('owner/documents.page.expiring_soon_badge') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' => $doc->uploadedByRole === 'client',
                                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $doc->uploadedByRole !== 'client',
                                ])>
                                    {{ $doc->uploadedByRole === 'client' ? __('owner/documents.uploader.you') : __('owner/documents.uploader.stable') }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $doc->createdAt->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ $this->downloadUrl($doc) }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ __('owner/documents.form.download') }}
                                </a>
                                @if ($this->canDelete($doc))
                                    <span class="text-xs text-gray-400">·</span>
                                    <button
                                        wire:click="deleteDocument({{ \Illuminate\Support\Js::from($doc->id) }})"
                                        wire:confirm="{{ __('owner/documents.form.delete_confirm') }}"
                                        type="button"
                                        class="text-xs text-rose-600 hover:underline dark:text-rose-400"
                                    >
                                        {{ __('owner/documents.form.delete') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
