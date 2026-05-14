@php
    /**
     * Topbar pomocy + zgłaszania błędów. Renderowane via PanelsRenderHook::TOPBAR_END
     * w AppPanelProvider/AdminPanelProvider. Dwa przyciski:
     *   ?  → /app/help (centrum pomocy)
     *  bug → modal Alpine.js → POST /bug-reports → Todoist
     *
     * Modal w scoped inline CSS (.hb-*) — Filament 3 ma własny pre-built
     * CSS bez naszych custom utility, więc Tailwind classes z gridem,
     * max-w-md, brand color itd. nie kompilowały się. Inline CSS gwarantuje
     * identyczny wygląd bez konieczności custom Filament theme.
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
            submit: @js(__('pages.help.bug_report.submit')),
            submitting: @js('...'),
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

    {{-- Modal — teleportowany do body, inline CSS (.hb-* scope) --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition.opacity
            class="hb-overlay"
            x-cloak
            @keydown.escape.window="open = false"
        >
            <div
                @click.outside="open = false"
                class="hb-modal"
                x-transition:enter="hb-modal--enter"
                x-transition:enter-start="hb-modal--enter-start"
                x-transition:enter-end="hb-modal--enter-end"
            >
                <header class="hb-header">
                    <div class="hb-header__text">
                        <h2 class="hb-title">{{ __('pages.help.bug_report.title') }}</h2>
                        <p class="hb-lead">{{ __('pages.help.bug_report.lead') }}</p>
                    </div>
                    <button type="button" @click="open = false" class="hb-close" aria-label="{{ __('pages.help.bug_report.cancel') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <form @submit.prevent="submit" class="hb-form">
                    <div class="hb-body">
                        {{-- Rodzaj — segmentowany toggle 2-kolumnowy --}}
                        <div>
                            <label class="hb-label">{{ __('pages.help.bug_report.kind_label') }}</label>
                            <div class="hb-kind-grid">
                                <label class="hb-kind" :class="form.kind === 'bug' ? 'is-active' : ''">
                                    <input type="radio" value="bug" x-model="form.kind">
                                    <span class="hb-kind__emoji">🐛</span>
                                    <span>{{ __('pages.help.bug_report.kind_bug') }}</span>
                                </label>
                                <label class="hb-kind" :class="form.kind === 'idea' ? 'is-active' : ''">
                                    <input type="radio" value="idea" x-model="form.kind">
                                    <span class="hb-kind__emoji">💡</span>
                                    <span>{{ __('pages.help.bug_report.kind_idea') }}</span>
                                </label>
                            </div>
                        </div>

                        {{-- Tytuł --}}
                        <div>
                            <label for="bug-subject" class="hb-label">{{ __('pages.help.bug_report.subject_label') }}</label>
                            <input
                                id="bug-subject"
                                x-ref="subject"
                                x-model="form.subject"
                                required
                                maxlength="160"
                                type="text"
                                placeholder="{{ __('pages.help.bug_report.subject_placeholder') }}"
                                class="hb-input"
                            >
                        </div>

                        {{-- Opis --}}
                        <div>
                            <label for="bug-desc" class="hb-label">{{ __('pages.help.bug_report.description_label') }}</label>
                            <textarea
                                id="bug-desc"
                                x-model="form.description"
                                required
                                maxlength="5000"
                                rows="4"
                                placeholder="{{ __('pages.help.bug_report.description_placeholder') }}"
                                class="hb-input hb-textarea"
                            ></textarea>
                        </div>

                        {{-- Screenshot --}}
                        <div>
                            <label for="bug-screen" class="hb-label">{{ __('pages.help.bug_report.screenshot_label') }}</label>
                            <input
                                id="bug-screen"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                @change="form.screenshot = $event.target.files[0]"
                                class="hb-file"
                            >
                        </div>

                        {{-- Status message + error detail --}}
                        <template x-if="message">
                            <div class="hb-status" :class="error ? 'is-error' : 'is-success'">
                                <p x-text="message"></p>
                                <template x-if="error && detail">
                                    <details class="hb-detail" open>
                                        <summary>
                                            <span>Szczegóły z serwera</span>
                                            <button type="button"
                                                    @click.prevent.stop="copyDetail"
                                                    class="hb-copy">
                                                <span x-text="copied ? '✓ skopiowano' : 'Kopiuj'"></span>
                                            </button>
                                        </summary>
                                        <pre x-text="detail"></pre>
                                    </details>
                                </template>
                            </div>
                        </template>
                    </div>

                    <footer class="hb-footer">
                        <button type="button" @click="open = false" class="hb-btn hb-btn--secondary">
                            {{ __('pages.help.bug_report.cancel') }}
                        </button>
                        <button type="submit" :disabled="submitting" class="hb-btn hb-btn--primary">
                            <svg x-show="submitting" class="hb-spinner" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                                <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <span x-text="submitting ? labels.submitting : labels.submit"></span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </template>
</div>

@once
    <style>
        /* Scoped do .hb-* żeby nie kolidowało z resztą panelu Filamenta */
        .hb-overlay {
            position: fixed; inset: 0; z-index: 100;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,.5);
            padding: 1rem;
        }
        .hb-modal {
            display: flex; flex-direction: column;
            width: 100%; max-width: 28rem; max-height: 90vh;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,.25);
            overflow: hidden;
            font-family: inherit; color: #1f2937;
        }
        .hb-modal--enter { transition: all .15s ease-out; }
        .hb-modal--enter-start { opacity: 0; transform: scale(.95); }
        .hb-modal--enter-end { opacity: 1; transform: scale(1); }

        .hb-header {
            display: flex; align-items: flex-start; gap: .75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .hb-header__text { flex: 1; min-width: 0; }
        .hb-title { margin: 0; font-size: 1rem; font-weight: 600; color: #111827; }
        .hb-lead { margin: .15rem 0 0; font-size: .8rem; color: #6b7280; line-height: 1.45; }
        .hb-close {
            flex-shrink: 0; padding: .25rem;
            background: transparent; border: 0; color: #9ca3af; cursor: pointer;
            border-radius: 6px;
        }
        .hb-close:hover { background: #f3f4f6; color: #4b5563; }
        .hb-close svg { width: 20px; height: 20px; }

        .hb-form { display: flex; flex-direction: column; overflow: hidden; }
        .hb-body {
            padding: 1rem 1.25rem;
            overflow-y: auto;
            display: flex; flex-direction: column; gap: 1rem;
        }

        .hb-label {
            display: block;
            font-size: .7rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: .04em;
            color: #6b7280;
            margin-bottom: .35rem;
        }

        /* Segmentowany kind selector */
        .hb-kind-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }
        .hb-kind {
            display: flex; align-items: center; justify-content: center;
            gap: .4rem;
            padding: .55rem .75rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            font-size: .875rem; font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            transition: all .15s;
            user-select: none;
        }
        .hb-kind:hover { border-color: #d1d5db; }
        .hb-kind.is-active {
            border-color: #A8956B;
            background: #f7f1e3;
            color: #3D2E22;
        }
        .hb-kind input { position: absolute; opacity: 0; pointer-events: none; }
        .hb-kind__emoji { font-size: 1rem; }

        /* Inputy */
        .hb-input {
            display: block; width: 100%;
            padding: .55rem .75rem;
            border: 1px solid #d1d5db; border-radius: 10px;
            background: #fff;
            font-size: .875rem; font-family: inherit;
            color: #111827;
            transition: border-color .15s, box-shadow .15s;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .hb-input::placeholder { color: #9ca3af; }
        .hb-input:focus {
            outline: none;
            border-color: #A8956B;
            box-shadow: 0 0 0 3px rgba(168,149,107,.25);
        }
        .hb-textarea { resize: vertical; min-height: 5rem; line-height: 1.5; }

        /* File input */
        .hb-file {
            display: block; width: 100%;
            font-size: .8rem; color: #6b7280;
            font-family: inherit;
        }
        .hb-file::file-selector-button {
            margin-right: .75rem;
            padding: .35rem .75rem;
            border: 0; border-radius: 6px;
            background: #f7f1e3;
            font-size: .75rem; font-weight: 600;
            color: #6e5b3a;
            cursor: pointer;
            transition: background .15s;
            font-family: inherit;
        }
        .hb-file::file-selector-button:hover { background: #efe5cc; }

        /* Status */
        .hb-status {
            border: 1px solid; border-radius: 10px;
            padding: .65rem .85rem;
            font-size: .8rem;
        }
        .hb-status p { margin: 0; }
        .hb-status.is-success { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
        .hb-status.is-error { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
        .hb-detail { margin-top: .4rem; }
        .hb-detail summary {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            cursor: pointer; list-style: none;
            font-size: .72rem; color: #b91c1c;
        }
        .hb-detail summary::-webkit-details-marker { display: none; }
        .hb-detail summary:hover { text-decoration: underline; }
        .hb-copy {
            padding: .15rem .45rem;
            border: 1px solid #fca5a5; border-radius: 4px;
            background: transparent;
            font-size: .65rem; font-weight: 600;
            color: #b91c1c;
            cursor: pointer;
            font-family: inherit;
        }
        .hb-copy:hover { background: #fee2e2; }
        .hb-detail pre {
            margin: .4rem 0 0;
            max-height: 12rem; overflow: auto;
            white-space: pre-wrap; word-break: break-all;
            padding: .5rem; border-radius: 6px;
            background: #fee2e2;
            font-size: .68rem; line-height: 1.35;
            color: #7f1d1d;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        /* Footer */
        .hb-footer {
            display: flex; justify-content: flex-end; gap: .5rem;
            padding: .75rem 1.25rem;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .hb-btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem 1rem;
            border-radius: 8px;
            font-size: .875rem; font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            font-family: inherit;
            border: 1px solid transparent;
        }
        .hb-btn:disabled { opacity: .6; cursor: not-allowed; }
        .hb-btn--secondary { background: #fff; border-color: #d1d5db; color: #374151; }
        .hb-btn--secondary:hover { background: #f9fafb; }
        .hb-btn--primary { background: #A8956B; color: #fff; }
        .hb-btn--primary:hover:not(:disabled) { background: #8f7f5b; }
        .hb-spinner { width: 16px; height: 16px; animation: hb-spin 1s linear infinite; }
        @keyframes hb-spin { to { transform: rotate(360deg); } }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .hb-modal { background: #1f1a17; color: #e9e2d3; }
            .hb-header, .hb-footer { border-color: #4a3d31; }
            .hb-footer { background: #2a221c; }
            .hb-title { color: #f7f4ef; }
            .hb-lead, .hb-label { color: #c8b8a4; }
            .hb-close:hover { background: #2a221c; color: #f7f4ef; }
            .hb-kind { background: #2a221c; border-color: #4a3d31; color: #c8b8a4; }
            .hb-kind:hover { border-color: #5a4d3f; }
            .hb-kind.is-active { background: rgba(168,149,107,.2); color: #f7f4ef; }
            .hb-input { background: #2a221c; border-color: #4a3d31; color: #f7f4ef; }
            .hb-input::placeholder { color: #8b7d6a; }
            .hb-input:focus { border-color: #A8956B; box-shadow: 0 0 0 3px rgba(168,149,107,.3); }
            .hb-file { color: #c8b8a4; }
            .hb-file::file-selector-button { background: rgba(168,149,107,.2); color: #d4b896; }
            .hb-file::file-selector-button:hover { background: rgba(168,149,107,.3); }
            .hb-btn--secondary { background: #2a221c; border-color: #4a3d31; color: #e9e2d3; }
            .hb-btn--secondary:hover { background: #3a2e22; }
            .hb-status.is-success { background: rgba(6,95,70,.25); border-color: rgba(16,185,129,.45); color: #6ee7b7; }
            .hb-status.is-error { background: rgba(127,29,29,.25); border-color: rgba(220,38,38,.45); color: #fca5a5; }
        }

        @media (max-width: 640px) {
            .hb-overlay { padding: .5rem; }
            .hb-header, .hb-body, .hb-footer { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>

    <script>
        window.bugReporter = function (config) {
            return {
                open: false,
                submitting: false,
                error: false,
                message: '',
                detail: '',
                copied: false,
                labels: config.labels,
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
