@php
    $tenant = app(\App\Tenancy\TenantManager::class)->current();
    if (! $tenant || ! $tenant->isTransporter()) return;
    $status = $tenant->verification_status;
    if (! $status || $status->isVerified()) return;
@endphp

<div class="bg-amber-50 dark:bg-amber-900/30 border-b border-amber-300 dark:border-amber-700 px-4 py-3 text-sm">
    <div class="flex items-center justify-between gap-3 max-w-full">
        <div class="flex items-start gap-2">
            <span class="text-amber-600 dark:text-amber-400 text-lg leading-none mt-0.5">⚠</span>
            <div>
                <strong class="text-amber-900 dark:text-amber-100">{{ __('transport/banner.title') }}:</strong>
                <span class="text-amber-800 dark:text-amber-200">
                    @if ($status->value === 'pending')
                        {{ __('transport/banner.pending') }}
                    @elseif ($status->value === 'under_review')
                        {{ __('transport/banner.under_review') }}
                    @elseif ($status->value === 'rejected')
                        {{ __('transport/banner.rejected') }}
                    @endif
                </span>
            </div>
        </div>
        <a href="{{ route('filament.transport.pages.transporter-documents') }}"
           class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-bold whitespace-nowrap">
            {{ __('transport/banner.cta') }}
        </a>
    </div>
</div>
