<x-filament-panels::page>
    @php
        $statusColors = [
            'pending' => 'gray',
            'under_review' => 'info',
            'verified' => 'success',
            'rejected' => 'danger',
        ];
    @endphp

    <div class="rounded-lg p-4 border {{ $verificationStatus->value === 'verified' ? 'border-green-300 bg-green-50 dark:bg-green-900/30' : ($verificationStatus->value === 'rejected' ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/30' : 'border-amber-300 bg-amber-50 dark:bg-amber-900/30') }}">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="font-bold text-base">{{ __('transport/documents.status.heading') }}: {{ $verificationStatus->label() }}</div>
                <div class="text-sm mt-1 opacity-80">
                    @if ($verificationStatus->value === 'verified')
                        {{ __('transport/documents.status.verified_body') }}
                    @elseif ($verificationStatus->value === 'under_review')
                        {{ __('transport/documents.status.under_review_body') }}
                    @elseif ($verificationStatus->value === 'rejected')
                        @php($rejectedCount = collect($docs)->filter(fn ($d) => $d && $d->status === 'rejected')->count())
                        {{ __('transport/documents.status.rejected_body') }}
                        @if ($rejectedCount > 0)
                            <span class="font-semibold text-rose-700 dark:text-rose-300">
                                {{ trans_choice('transport/documents.status.rejected_count', $rejectedCount, ['count' => $rejectedCount]) }}
                            </span>
                        @endif
                    @else
                        {{ __('transport/documents.status.pending_body', ['count' => max(0, $missingRequired)]) }}
                    @endif
                </div>
            </div>
            @if ($verificationStatus->value === 'pending' && $missingRequired > 0)
                <div class="rounded-full bg-amber-500 text-white px-3 py-1 text-sm font-bold whitespace-nowrap">
                    {{ __('transport/documents.status.missing_badge', ['count' => $missingRequired]) }}
                </div>
            @elseif ($verificationStatus->value === 'rejected')
                <div class="rounded-full bg-rose-600 text-white px-3 py-1 text-sm font-bold whitespace-nowrap">
                    {{ __('transport/documents.status.rejected_badge') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Checklist PWL widget: pokazuje X/Y dokumentów zweryfikowanych
         + listę co brakuje. Reużywany w master adminie. --}}
    <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <div class="font-semibold text-sm mb-2">{{ __('transport/documents.checklist.heading') }}</div>
        <div class="text-sm text-gray-700 dark:text-gray-300 mb-3">
            {{ __('transport/documents.checklist.progress', ['done' => $checklist->verifiedCount, 'total' => $checklist->totalRequired]) }}
        </div>
        <ul class="space-y-1 text-sm">
            @foreach ($checklist->items as $item)
                <li class="flex items-center gap-2">
                    @if ($item->isVerified())
                        <span class="text-green-600 dark:text-green-400">✓</span>
                    @elseif ($item->status === 'pending')
                        <span class="text-amber-500">●</span>
                    @elseif ($item->status === 'rejected')
                        <span class="text-rose-600">✗</span>
                    @else
                        <span class="text-gray-400">○</span>
                    @endif
                    <span class="{{ $item->isVerified() ? 'text-gray-600 dark:text-gray-400' : 'font-medium' }}">{{ $item->label }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    @if (! empty($pwlRequiredTypes))
        <h3 class="font-semibold text-base mt-6 mb-2">{{ __('transport/documents.section.pwl_required') }}</h3>
        <div class="space-y-4">
            @foreach ($pwlRequiredTypes as $type)
                @include('filament.transport.pages._document-card', [
                    'type' => $type,
                    'doc' => $docs[$type->value] ?? null,
                    'isRequired' => true,
                    'hasExpiry' => $type->expiresByLaw(),
                    'statusColors' => $statusColors,
                ])
            @endforeach
        </div>
    @endif

    @if (! empty($optionalTypes))
        <h3 class="font-semibold text-base mt-6 mb-2">{{ __('transport/documents.section.pwl_optional') }}</h3>
        <div class="space-y-4">
            @foreach ($optionalTypes as $type)
                @include('filament.transport.pages._document-card', [
                    'type' => $type,
                    'doc' => $docs[$type->value] ?? null,
                    'isRequired' => false,
                    'hasExpiry' => $type->expiresByLaw(),
                    'statusColors' => $statusColors,
                ])
            @endforeach
        </div>
    @endif

    @if (! empty($legacyTypes))
        <h3 class="font-semibold text-base mt-6 mb-2 text-gray-500">{{ __('transport/documents.section.legacy') }}</h3>
        <div class="space-y-4 opacity-80">
            @foreach ($legacyTypes as $type)
                @include('filament.transport.pages._document-card', [
                    'type' => $type,
                    'doc' => $docs[$type->value] ?? null,
                    'isRequired' => false,
                    'hasExpiry' => $type->expiresByLaw(),
                    'statusColors' => $statusColors,
                ])
            @endforeach
        </div>
    @endif

    <p class="text-xs text-gray-500 mt-4">
        {{ __('transport/documents.footer.allowed_formats') }}
    </p>
</x-filament-panels::page>
