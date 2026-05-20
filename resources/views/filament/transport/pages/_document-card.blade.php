{{--
    Wewnętrzny partial — karta pojedynczego dokumentu w panelu transportera.
    Wyodrębniony z transporter-documents.blade.php gdy dodaliśmy sekcje (PWL
    required / opcjonalne / legacy) żeby uniknąć duplikacji.

    Wymagane zmienne: $type, $doc, $isRequired, $hasExpiry, $statusColors.
--}}
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
            {{-- Per-typ helper text (specyfika PWL — np. „T1 vs T2"). --}}
            @if ($type === \App\Enums\TransporterDocumentType::PwlAuthorizationT1 || $type === \App\Enums\TransporterDocumentType::PwlAuthorizationT2)
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">{{ __('transport/documents.helper.pwl_authorization_choice') }}</p>
            @elseif ($type === \App\Enums\TransporterDocumentType::PwlVehicleApprovalCertificate)
                <p class="text-xs text-gray-500 mt-1">{{ __('transport/documents.helper.pwl_vehicle_per_vehicle') }}</p>
            @elseif ($type === \App\Enums\TransporterDocumentType::WashDisinfectionLog)
                <p class="text-xs text-gray-500 mt-1">{{ __('transport/documents.helper.wash_log_period') }}</p>
            @endif
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

    @php
        // Upload form pokazujemy gdy:
        //   - brak dokumentu (jeszcze nic nie wgrane)
        //   - dokument odrzucony przez master admina (re-upload)
        //   - dokument zweryfikowany ALE wygasły lub niedługo wygaśnie (replace expiring)
        $allowReupload = ! $doc
            || $doc->status === 'rejected'
            || ($doc->status === 'verified' && ($doc->isExpired() || $doc->isExpiringSoon()));
    @endphp

    @if ($allowReupload)
        <form wire:submit.prevent="uploadDocument('{{ $type->value }}')" class="mt-3 grid gap-2 sm:grid-cols-3">
            @if ($doc && $doc->status === 'verified')
                <div class="sm:col-span-3 p-2 text-xs rounded bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200">
                    {{ $doc->isExpired() ? __('transport/documents.helper.replace_expired') : __('transport/documents.helper.replace_expiring') }}
                </div>
            @endif
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
                    {{ $doc && $doc->status === 'verified' ? __('transport/documents.action.replace') : __('transport/documents.action.upload') }}
                </x-filament::button>
            </div>
        </form>
    @endif
</div>
