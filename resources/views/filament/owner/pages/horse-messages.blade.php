<x-filament-panels::page>
    {{-- Hero: stajnia + stan dostępu --}}
    <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-900/20">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300">
                    {{ __('owner/messages.page.thread_with') }}
                </div>
                <div class="font-semibold">{{ $this->stableTenant->name }}</div>
            </div>
            @if (! $this->canSend)
                <div class="text-xs text-amber-700 dark:text-amber-300">
                    {{ __('owner/messages.access.send_requires_active_boarding') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Thread --}}
    @if (empty($this->thread))
        <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center dark:border-gray-800">
            <div class="text-base font-semibold">{{ __('owner/messages.page.empty_heading') }}</div>
            <div class="mt-2 text-sm text-gray-500">{{ __('owner/messages.page.empty_description') }}</div>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->thread as $msg)
                @php($isOwner = $msg->direction === 'from_client')
                <div class="flex {{ $isOwner ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-lg border p-3 shadow-sm
                        @if ($isOwner) border-primary-200 bg-primary-50 dark:border-primary-800 dark:bg-primary-900/20
                        @else border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900/40 @endif
                    ">
                        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                            <span class="font-medium">
                                {{ $msg->senderName ?? ($isOwner ? __('owner/horse_timeline.actor.owner') : __('owner/horse_timeline.actor.stable')) }}
                            </span>
                            <span>·</span>
                            <span>{{ $msg->sentAt->format('Y-m-d H:i') }}</span>
                        </div>
                        @if ($msg->subject)
                            <div class="mb-1 font-semibold">{{ $msg->subject }}</div>
                        @endif
                        <div class="whitespace-pre-wrap text-sm">{{ $msg->body }}</div>

                        {{-- Read receipt (tylko na własnych wiadomościach owner'a, parity z chat UX) --}}
                        @if ($isOwner)
                            <div class="mt-1 flex items-center justify-end gap-1 text-[11px] text-gray-500">
                                @if ($msg->readByStableAt !== null)
                                    <x-filament::icon icon="heroicon-s-check-badge" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                    <span class="text-emerald-700 dark:text-emerald-400">
                                        {{ __('owner/messages.receipt.read_by_stable_at', ['time' => $msg->readByStableAt->format('Y-m-d H:i')]) }}
                                    </span>
                                @else
                                    <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ __('owner/messages.receipt.sent_pending_read') }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($msg->attachmentCount > 0)
                            <div class="mt-2 space-y-1 border-t pt-2 {{ $isOwner ? 'border-primary-200 dark:border-primary-800' : 'border-gray-200 dark:border-gray-800' }}">
                                @foreach ($msg->attachments as $idx => $attachment)
                                    @php($downloadUrl = $this->downloadUrl($msg->id, (int) $idx))
                                    @if ($this->isImageAttachment($attachment))
                                        <a href="{{ $downloadUrl }}" target="_blank" class="block">
                                            <img
                                                src="{{ $downloadUrl }}"
                                                alt="{{ $attachment['original_name'] ?? 'image' }}"
                                                class="max-h-48 max-w-full rounded border border-gray-200 dark:border-gray-800"
                                                loading="lazy"
                                            />
                                        </a>
                                    @else
                                        <a
                                            href="{{ $downloadUrl }}"
                                            target="_blank"
                                            class="flex items-center gap-2 text-xs text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                                            <span>{{ $attachment['original_name'] ?? 'attachment' }}</span>
                                            @if (isset($attachment['size']))
                                                <span class="text-gray-500">({{ $this->formatFileSize((int) $attachment['size']) }})</span>
                                            @endif
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Send form --}}
    <form wire:submit="send" class="space-y-3">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit" :disabled="! $this->canSend" icon="heroicon-o-paper-airplane">
                {{ __('owner/messages.form.send') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
