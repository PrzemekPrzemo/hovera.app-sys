<x-filament-panels::page>
    @php
        $statusColors = [
            'pending' => 'gray',
            'under_review' => 'info',
            'verified' => 'success',
            'rejected' => 'danger',
        ];
    @endphp

    <div class="rounded-lg p-4 border {{ $verificationStatus->value === 'verified' ? 'border-green-300 bg-green-50 dark:bg-green-900/30' : 'border-amber-300 bg-amber-50 dark:bg-amber-900/30' }}">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="font-bold text-base">{{ __('transport/documents.status.heading') }}: {{ $verificationStatus->label() }}</div>
                <div class="text-sm mt-1 opacity-80">
                    @if ($verificationStatus->value === 'verified')
                        {{ __('transport/documents.status.verified_body') }}
                    @elseif ($verificationStatus->value === 'under_review')
                        {{ __('transport/documents.status.under_review_body') }}
                    @elseif ($verificationStatus->value === 'rejected')
                        {{ __('transport/documents.status.rejected_body') }}
                    @else
                        {{ __('transport/documents.status.pending_body', ['count' => $missingRequired]) }}
                    @endif
                </div>
            </div>
            @if ($verificationStatus->value === 'pending' && $missingRequired > 0)
                <div class="rounded-full bg-amber-500 text-white px-3 py-1 text-sm font-bold whitespace-nowrap">
                    {{ __('transport/documents.status.missing_badge', ['count' => $missingRequired]) }}
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-4 mt-6">
        @foreach ($documentTypes as $type)
            @php
                $doc = $docs[$type->value] ?? null;
                $isRequired = $type->isRequired();
                $hasExpiry = $type->expiresByLaw();
            @endphp
            <div class="rounded-lg border border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-800 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-base">{{ $type->label() }}</h3>
                            @if ($isRequired)
                                <span class="text-xs px-1.5 py-0.5 bg-rose-100 text-rose-700 rounded">{{ __('transport/documents.label.required') }}</span>
                            @else
                                <span class="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">{{ __('transport/documents.label.optional') }}</span>
                            @endif
                            @if ($doc)
                                @php $color = $statusColors[$doc->status] ?? 'gray'; @endphp
                                <span class="text-xs px-1.5 py-0.5 rounded bg-{{ $color }}-100 text-{{ $color }}-700">
                                    {{ __('enums.verification_status.'.($doc->status === 'verified' ? 'verified' : ($doc->status === 'rejected' ? 'rejected' : 'pending'))) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $type->description() }}</p>
                    </div>
                </div>

                @if ($doc)
                    <div class="mt-3 p-3 rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $doc->original_filename ?? basename($doc->file_path) }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ __('transport/documents.label.uploaded_at') }}: {{ $doc->created_at?->format('Y-m-d H:i') }}
                                    @if ($doc->file_size)
                                        · {{ number_format($doc->file_size / 1024, 0) }} KB
                                    @endif
                                </div>
                                @if ($doc->expires_at)
                                    <div class="text-xs mt-0.5 {{ $doc->isExpired() ? 'text-rose-600 font-semibold' : ($doc->isExpiringSoon() ? 'text-amber-600' : 'text-gray-500') }}">
                                        {{ __('transport/documents.label.expires_at') }}: {{ $doc->expires_at->format('Y-m-d') }}
                                        @if ($doc->isExpired())
                                            · {{ __('transport/documents.label.expired') }}
                                        @elseif ($doc->isExpiringSoon())
                                            · {{ __('transport/documents.label.expiring_soon') }}
                                        @endif
                                    </div>
                                @endif
                                @if ($doc->status === 'rejected' && $doc->rejection_reason)
                                    <div class="mt-2 text-xs text-rose-700 bg-rose-50 dark:bg-rose-950/40 p-2 rounded">
                                        <strong>{{ __('transport/documents.label.rejection_reason') }}:</strong> {{ $doc->rejection_reason }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                @if ($doc->status !== 'verified')
                                    <button wire:click="deleteDocument('{{ $doc->id }}')"
                                            wire:confirm="{{ __('transport/documents.confirm.delete') }}"
                                            class="text-xs px-2 py-1 rounded border border-rose-300 text-rose-700 hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300">
                                        {{ __('transport/documents.action.delete') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if (! $doc || $doc->status === 'rejected')
                    <form wire:submit.prevent="uploadDocument('{{ $type->value }}')" class="mt-3 grid gap-2 sm:grid-cols-3">
                        <div class="sm:col-span-3">
                            <input type="file" wire:model="files.{{ $type->value }}" accept=".pdf,.jpg,.jpeg,.png"
                                   class="block w-full text-sm border border-gray-300 dark:border-gray-700 rounded-md p-1.5 bg-white dark:bg-gray-900">
                            @error('files.'.$type->value)
                                <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @if ($hasExpiry)
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('transport/documents.label.issued_at') }}</label>
                                <input type="date" wire:model="issuedAt.{{ $type->value }}"
                                       class="block w-full text-sm border border-gray-300 dark:border-gray-700 rounded-md p-1.5 bg-white dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('transport/documents.label.expires_at') }}</label>
                                <input type="date" wire:model="expiresAt.{{ $type->value }}"
                                       class="block w-full text-sm border border-gray-300 dark:border-gray-700 rounded-md p-1.5 bg-white dark:bg-gray-900">
                            </div>
                        @endif
                        <div class="{{ $hasExpiry ? 'sm:col-span-1' : 'sm:col-span-3' }} flex items-end justify-end">
                            <x-filament::button type="submit" size="sm" icon="heroicon-o-arrow-up-tray">
                                {{ __('transport/documents.action.upload') }}
                            </x-filament::button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500 mt-4">
        {{ __('transport/documents.footer.allowed_formats') }}
    </p>
</x-filament-panels::page>
