{{-- Address autocomplete combobox.
     Includes a self-contained vanilla JS script that auto-attaches to any
     <input data-places-autocomplete="panel|public"> on the page.

     - Min 3 chars before suggestions show
     - 250ms debounce
     - Arrow up/down + Enter + Esc + click selection
     - Fetches GET /api/transport/places/suggest?q=...&context=... and
       renders a dropdown.

     Wire-in:
       <input type="text" data-places-autocomplete="public" autocomplete="off">
       @include('components.places-autocomplete-script')
       (or just include once globally — script is idempotent).

     Filament:
       Forms\Components\TextInput::make('from_address')
         ->extraInputAttributes(['data-places-autocomplete' => 'panel', 'autocomplete' => 'off']);
       (the script is auto-included via Filament render hook in
        AppServiceProvider). --}}
@once
    <style>
        .hovera-places-suggest {
            position: absolute;
            z-index: 9999;
            background: #fff;
            color: #111;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,.12);
            max-height: 280px;
            overflow-y: auto;
            font-size: 14px;
            min-width: 240px;
        }
        .hovera-places-suggest[data-empty="true"] { display: none; }
        .hovera-places-suggest__item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f4f4f5;
            line-height: 1.35;
        }
        .hovera-places-suggest__item:last-child { border-bottom: none; }
        .hovera-places-suggest__item--active,
        .hovera-places-suggest__item:hover {
            background: #2563eb;
            color: #fff;
        }
        @media (prefers-color-scheme: dark) {
            .hovera-places-suggest { background: #1f2937; color: #f3f4f6; border-color: #374151; }
            .hovera-places-suggest__item { border-bottom-color: #374151; }
        }
    </style>
    <script>
        (function () {
            if (window.__hoveraPlacesAutocompleteLoaded) return;
            window.__hoveraPlacesAutocompleteLoaded = true;

            const ENDPOINT = '{{ route('api.transport.places.suggest') }}';
            const MIN_LEN = 3;
            const DEBOUNCE_MS = 250;

            function debounce(fn, delay) {
                let t;
                return function (...args) {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, args), delay);
                };
            }

            function attach(input) {
                if (input.dataset.placesAutocompleteAttached === '1') return;
                input.dataset.placesAutocompleteAttached = '1';
                input.setAttribute('autocomplete', 'off');

                const context = input.dataset.placesAutocomplete || 'public';
                const list = document.createElement('div');
                list.className = 'hovera-places-suggest';
                list.dataset.empty = 'true';
                list.setAttribute('role', 'listbox');
                document.body.appendChild(list);

                let activeIndex = -1;
                let items = [];

                function positionList() {
                    const rect = input.getBoundingClientRect();
                    list.style.left = (window.scrollX + rect.left) + 'px';
                    list.style.top = (window.scrollY + rect.bottom + 2) + 'px';
                    list.style.width = rect.width + 'px';
                }

                function hide() {
                    list.dataset.empty = 'true';
                    list.innerHTML = '';
                    items = [];
                    activeIndex = -1;
                }

                function setActive(idx) {
                    const children = list.querySelectorAll('.hovera-places-suggest__item');
                    children.forEach((el, i) => {
                        el.classList.toggle('hovera-places-suggest__item--active', i === idx);
                    });
                    activeIndex = idx;
                }

                function pickItem(idx) {
                    const item = items[idx];
                    if (!item) return;
                    setInputValue(input, item.label);
                    hide();
                }

                function setInputValue(el, value) {
                    const lastValue = el.value;
                    el.value = value;
                    // Livewire/Filament listens to 'input' event. React-style
                    // value setter detour żeby Livewire wire:model wyłapał zmianę.
                    const tracker = el._valueTracker;
                    if (tracker) tracker.setValue(lastValue);
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const fetchSuggestions = debounce(async function (query) {
                    if (query.length < MIN_LEN) { hide(); return; }
                    try {
                        const url = ENDPOINT + '?q=' + encodeURIComponent(query) + '&context=' + encodeURIComponent(context);
                        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!resp.ok) { hide(); return; }
                        const data = await resp.json();
                        items = Array.isArray(data.items) ? data.items : [];
                        if (items.length === 0) { hide(); return; }
                        positionList();
                        list.innerHTML = items.map((it, i) =>
                            '<div class="hovera-places-suggest__item" role="option" data-idx="' + i + '">' +
                                escapeHtml(it.label) +
                            '</div>'
                        ).join('');
                        list.dataset.empty = 'false';
                        list.querySelectorAll('.hovera-places-suggest__item').forEach((el) => {
                            el.addEventListener('mousedown', (e) => { e.preventDefault(); pickItem(parseInt(el.dataset.idx, 10)); });
                        });
                    } catch (e) {
                        hide();
                    }
                }, DEBOUNCE_MS);

                function escapeHtml(s) {
                    return String(s).replace(/[&<>"']/g, (c) => ({
                        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                    })[c]);
                }

                input.addEventListener('input', function () {
                    fetchSuggestions(input.value.trim());
                });
                input.addEventListener('keydown', function (e) {
                    if (list.dataset.empty === 'true') return;
                    if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(activeIndex + 1, items.length - 1)); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(activeIndex - 1, 0)); }
                    else if (e.key === 'Enter') {
                        if (activeIndex >= 0) { e.preventDefault(); pickItem(activeIndex); }
                    }
                    else if (e.key === 'Escape') { hide(); }
                });
                input.addEventListener('blur', function () { setTimeout(hide, 150); });
                window.addEventListener('scroll', positionList, true);
                window.addEventListener('resize', positionList);
            }

            function scan(root) {
                (root || document).querySelectorAll('input[data-places-autocomplete]').forEach(attach);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => scan());
            } else {
                scan();
            }

            // Livewire/Filament re-renders inputs — rescan po każdej zmianie DOM.
            const observer = new MutationObserver((muts) => {
                muts.forEach((m) => m.addedNodes.forEach((n) => {
                    if (n.nodeType === 1) scan(n);
                }));
            });
            observer.observe(document.body, { childList: true, subtree: true });
        })();
    </script>
@endonce
