@php
    /**
     * Topbar pomocy + zgłaszania błędów. Renderowane via PanelsRenderHook::TOPBAR_END
     * w AppPanelProvider/AdminPanelProvider. Dwa przyciski:
     *   ?  → /app/help (centrum pomocy)
     *  bug → modal Alpine.js → POST /bug-reports → Todoist
     */
    $helpUrl = url('/app/help');
    $endpoint = route('bug-reports.store');
@endphp

<div
    x-data="bugReporter({
        endpoint: @js($endpoint),
        labels: {
            success: @js(__('pages.help.bug_report.success')),
            error: @js(__('pages.help.bug_report.error')),
        },
    })"
    class="fi-topbar-help-block flex items-center gap-1 px-1"
>
    {{-- Help center button --}}
    <a
        href="{{ $helpUrl }}"
        class="fi-icon-btn relative inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-50/80 hover:bg-gray-700/40 hover:text-white transition"
        title="{{ __('pages.help.topbar.help') }}"
        aria-label="{{ __('pages.help.topbar.help') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
        </svg>
    </a>

    {{-- Bug / suggestion button --}}
    <button
        type="button"
        @click="open = true; $nextTick(() => $refs.subject?.focus())"
        class="fi-icon-btn relative inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-50/80 hover:bg-gray-700/40 hover:text-white transition"
        title="{{ __('pages.help.topbar.report_bug') }}"
        aria-label="{{ __('pages.help.topbar.report_bug') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
    </button>

    {{-- Modal — Alpine, teleportowany do body żeby uciec z topbara --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            x-cloak
            @keydown.escape.window="open = false"
        >
            <div
                @click.outside="open = false"
                class="flex w-full max-w-md max-h-[90vh] flex-col rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
            >
                {{-- Sticky header — zawsze widoczny, nawet przy scrollu treści --}}
                <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('pages.help.bug_report.title') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('pages.help.bug_report.lead') }}
                        </p>
                    </div>
                    <button type="button" @click="open = false" aria-label="{{ __('pages.help.bug_report.cancel') }}"
                            class="shrink-0 rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="flex flex-col overflow-hidden">
                    <div class="overflow-y-auto px-5 py-4 space-y-4">
                        {{-- Rodzaj — segmentowany toggle (zawsze widoczne obie ramki) --}}
                        <div>
                            <label class="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('pages.help.bug_report.kind_label') }}</label>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <label class="flex items-center justify-center gap-2 rounded-lg border-2 px-3 py-2 text-sm font-medium cursor-pointer transition select-none"
                                       :class="form.kind === 'bug'
                                            ? 'border-primary-500 bg-primary-50 text-primary-900 dark:bg-primary-900/30 dark:text-primary-100'
                                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600'">
                                    <input type="radio" value="bug" x-model="form.kind" class="sr-only">
                                    <span>🐛</span>
                                    <span>{{ __('pages.help.bug_report.kind_bug') }}</span>
                                </label>
                                <label class="flex items-center justify-center gap-2 rounded-lg border-2 px-3 py-2 text-sm font-medium cursor-pointer transition select-none"
                                       :class="form.kind === 'idea'
                                            ? 'border-primary-500 bg-primary-50 text-primary-900 dark:bg-primary-900/30 dark:text-primary-100'
                                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600'">
                                    <input type="radio" value="idea" x-model="form.kind" class="sr-only">
                                    <span>💡</span>
                                    <span>{{ __('pages.help.bug_report.kind_idea') }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Tytuł --}}
                        <div>
                            <label for="bug-subject" class="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('pages.help.bug_report.subject_label') }}</label>
                            <input
                                id="bug-subject"
                                x-ref="subject"
                                x-model="form.subject"
                                required
                                maxlength="160"
                                type="text"
                                placeholder="{{ __('pages.help.bug_report.subject_placeholder') }}"
                                class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
                            >
                        </div>

                        {{-- Opis --}}
                        <div>
                            <label for="bug-desc" class="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('pages.help.bug_report.description_label') }}</label>
                            <textarea
                                id="bug-desc"
                                x-model="form.description"
                                required
                                maxlength="5000"
                                rows="4"
                                placeholder="{{ __('pages.help.bug_report.description_placeholder') }}"
                                class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
                            ></textarea>
                        </div>

                        {{-- Screenshot --}}
                        <div>
                            <label for="bug-screen" class="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('pages.help.bug_report.screenshot_label') }}</label>
                            <input
                                id="bug-screen"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                @change="form.screenshot = $event.target.files[0]"
                                class="mt-1.5 block w-full text-xs text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary-700 hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-primary-900/30 dark:file:text-primary-300 dark:hover:file:bg-primary-900/50"
                            >
                        </div>

                        {{-- Status message + error detail --}}
                        <template x-if="message">
                            <div :class="error ? 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/20' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-900/20'"
                                 class="rounded-lg border px-3 py-2">
                                <p class="text-sm" :class="error ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300'" x-text="message"></p>
                                <template x-if="error && detail">
                                    <details class="mt-1.5" open>
                                        <summary class="flex items-center justify-between cursor-pointer text-xs text-red-600 dark:text-red-400 hover:underline">
                                            <span>Szczegóły z serwera</span>
                                            <button type="button"
                                                    @click.prevent.stop="copyDetail"
                                                    class="ml-2 rounded border border-red-300 px-1.5 py-0.5 text-[10px] font-medium text-red-700 hover:bg-red-100 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/40">
                                                <span x-text="copied ? '✓ skopiowano' : 'Kopiuj'"></span>
                                            </button>
                                        </summary>
                                        <pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap break-all rounded bg-red-100 p-2 text-[11px] leading-snug text-red-900 dark:bg-red-900/40 dark:text-red-200" x-text="detail"></pre>
                                    </details>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Sticky footer z akcjami --}}
                    <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-900/50">
                        <button type="button" @click="open = false"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ __('pages.help.bug_report.cancel') }}
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                            <svg x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <span x-text="submitting ? '...' : @js(__('pages.help.bug_report.submit'))"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>

@once
    <script>
        window.bugReporter = function (config) {
            return {
                open: false,
                submitting: false,
                error: false,
                message: '',
                detail: '',
                copied: false,
                form: { kind: 'bug', subject: '', description: '', screenshot: null },
                async copyDetail() {
                    try {
                        await navigator.clipboard.writeText(this.detail);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 1500);
                    } catch (e) {
                        const ta = document.createElement('textarea');
                        ta.value = this.detail;
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); this.copied = true; setTimeout(() => { this.copied = false; }, 1500); } catch (_) {}
                        document.body.removeChild(ta);
                    }
                },
                async submit() {
                    this.submitting = true;
                    this.error = false;
                    this.message = '';
                    this.detail = '';

                    const csrf = document.querySelector('meta[name=csrf-token]')?.content
                        || document.querySelector('input[name=_token]')?.value
                        || '';

                    if (!csrf) {
                        this.error = true;
                        this.message = 'Brak tokenu CSRF na stronie — odśwież stronę i spróbuj ponownie.';
                        console.error('[bug-reporter] CSRF token meta tag missing');
                        this.submitting = false;
                        return;
                    }

                    const fd = new FormData();
                    fd.append('kind', this.form.kind);
                    fd.append('subject', this.form.subject);
                    fd.append('description', this.form.description);
                    fd.append('source_url', window.location.href);
                    if (this.form.screenshot) {
                        fd.append('screenshot', this.form.screenshot);
                    }

                    let res;
                    try {
                        res = await fetch(config.endpoint, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                            credentials: 'same-origin',
                        });
                    } catch (netErr) {
                        this.error = true;
                        this.message = 'Błąd sieci — sprawdź połączenie.';
                        this.detail = String(netErr);
                        console.error('[bug-reporter] network error', netErr);
                        this.submitting = false;
                        return;
                    }

                    let payload = null;
                    const rawText = await res.text();
                    try { payload = JSON.parse(rawText); } catch (_) {}

                    if (res.ok) {
                        this.message = config.labels.success;
                        this.form = { kind: 'bug', subject: '', description: '', screenshot: null };
                        const fileEl = document.getElementById('bug-screen');
                        if (fileEl) fileEl.value = '';
                        setTimeout(() => { this.open = false; this.message = ''; }, 1500);
                        this.submitting = false;
                        return;
                    }

                    this.error = true;
                    console.error('[bug-reporter] HTTP', res.status, payload || rawText);

                    if (res.status === 419) {
                        this.message = 'Sesja wygasła (419). Odśwież stronę i spróbuj ponownie.';
                    } else if (res.status === 422 && payload?.errors) {
                        const errs = Object.values(payload.errors).flat();
                        this.message = 'Błąd walidacji: ' + errs.join(' · ');
                    } else if (res.status === 401 || res.status === 403) {
                        this.message = 'Brak autoryzacji (' + res.status + '). Zaloguj się ponownie.';
                    } else if (res.status === 503 && payload?.error === 'integration_not_configured') {
                        this.message = 'Todoist nie jest skonfigurowany na serwerze (brak TODOIST_API_TOKEN w .env).';
                        this.detail = payload?.message || '';
                    } else if (res.status === 502) {
                        this.message = 'Todoist odrzucił zgłoszenie.';
                        this.detail = payload?.message || rawText;
                    } else if (res.status === 429) {
                        this.message = 'Za dużo zgłoszeń w krótkim czasie — spróbuj za chwilę.';
                    } else if (res.status >= 500) {
                        this.message = 'Błąd serwera (' + res.status + ').';
                        this.detail = payload?.message || rawText;
                    } else {
                        this.message = config.labels.error + ' (HTTP ' + res.status + ')';
                        this.detail = payload?.message || rawText;
                    }
                    this.submitting = false;
                },
            };
        };
    </script>
@endonce
