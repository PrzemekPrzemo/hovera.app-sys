<x-filament-panels::page>
    <form wire:submit="send" class="space-y-4">
        {{ $this->form }}

        <div class="flex justify-end gap-2">
            <x-filament::button color="gray" tag="a" :href="url('/admin/tenants/'.$tenantId.'/edit')">
                {{ __('admin/back-office.common.back') }}
            </x-filament::button>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                {{ __('admin/back-office.mailer.send') }}
            </x-filament::button>
        </div>
    </form>

    @if (count($history) > 0)
        <div class="mt-8">
            <h3 class="text-base font-semibold mb-2">{{ __('admin/back-office.mailer.history.title') }}</h3>
            <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('admin/back-office.mailer.history.col.sent_at') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('admin/back-office.mailer.history.col.subject') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('admin/back-office.mailer.history.col.template') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('admin/back-office.mailer.history.col.recipients') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($history as $row)
                            <tr>
                                <td class="px-3 py-2 text-xs">{{ $row['sent_at'] }}</td>
                                <td class="px-3 py-2">{{ $row['subject'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $row['template'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $row['recipients_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
