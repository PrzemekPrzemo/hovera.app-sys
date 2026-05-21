@php($notifications = $this->getNotifications())
@php($totalUnread = $this->getTotalUnreadCount())

<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('owner/dashboard.activity.heading')"
        :description="__('owner/dashboard.activity.description')"
    >
        <x-slot name="headerEnd">
            @if ($totalUnread > 0)
                <x-filament::button
                    wire:click="markAllRead"
                    size="xs"
                    color="gray"
                    icon="heroicon-o-check-circle"
                >
                    {{ __('owner/dashboard.activity.mark_all_read') }}
                </x-filament::button>
            @endif
        </x-slot>

        @if ($notifications->isEmpty())
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('owner/dashboard.activity.empty') }}
            </div>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($notifications as $notification)
                    @php($cls = $this->classesFor($notification))
                    @php($url = $this->urlFor($notification))
                    <li class="flex items-start gap-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $cls['badge_bg'] }}">
                            <x-filament::icon
                                :icon="$this->iconFor($notification)"
                                class="h-5 w-5 {{ $cls['icon_text'] }}"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="text-sm font-medium">{{ $this->labelFor($notification) }}</span>
                                <span class="text-xs text-gray-400">{{ $this->relativeTime($notification) }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-sm text-gray-600 dark:text-gray-300">
                                {{ $this->summaryFor($notification) }}
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs">
                                @if ($url)
                                    <a
                                        href="{{ $url }}"
                                        wire:click="markRead({{ \Illuminate\Support\Js::from($notification->id) }})"
                                        class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ __('owner/dashboard.activity.open') }}
                                    </a>
                                    <span class="text-gray-400">·</span>
                                @endif
                                <button
                                    wire:click="markRead({{ \Illuminate\Support\Js::from($notification->id) }})"
                                    type="button"
                                    class="text-gray-500 hover:underline dark:text-gray-400"
                                >
                                    {{ __('owner/dashboard.activity.mark_read') }}
                                </button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($totalUnread > $notifications->count())
                <div class="mt-3 text-center text-xs text-gray-500">
                    {{ __('owner/dashboard.activity.more', ['count' => $totalUnread - $notifications->count()]) }}
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
