{{-- Embed snippet HTML+CSS+JS — generowany dla transportera w panelu
     `/transport/embed-snippet`. Skopiuj i wklej do swojej strony.

     Wymagane zmienne (przekazywane przez EmbedSnippet Filament Page):
       - $tenantSlug   (string)
       - $apiToken     (string)
       - $apiUrl       (string)  np. https://app.hovera.app/api/transport/inquiry
       - $brandColor   (string)  np. '#A8956B' (z tenant branding)
       - $companyName  (string)  pokazywany w header'ze formularza

     Stany UI per HTTP status (wzorzec ze starego repo):
       - submit: button "Wysyłam..." + disable
       - 200: zielony banner "Zapytanie wysłane"
       - 422: czerwony banner z listą błędów walidacji
       - 429: czerwony banner "Za dużo zapytań"
       - network error: czerwony banner "Brak połączenia"
--}}
<!-- Wygenerowano z Hovera Transport — app.hovera.app/transport/embed-snippet -->
<div id="tk-inquiry-form" style="max-width:560px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,.06);color:#1F1A17;">
    <h3 style="margin:0 0 .25rem;font-size:1.25rem;color:{{ $brandColor }};">Zapytanie o transport koni</h3>
    <p style="margin:0 0 1rem;font-size:.88rem;color:#6b7280;">Wypełnij formularz — {{ $companyName }} odpowie z ofertą.</p>

    <div id="tk-inquiry-banner" style="display:none;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem;"></div>

    <form id="tk-inquiry-form-element" novalidate>
        <input type="hidden" name="transporter_slug" value="{{ $tenantSlug }}">

        <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label>
        </div>

        <div style="display:grid;gap:.75rem;grid-template-columns:1fr 1fr;margin-bottom:.75rem;">
            <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;">
                Imię i nazwisko
                <input type="text" name="customer_name" required maxlength="120" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;">
                E-mail
                <input type="email" name="customer_email" required maxlength="255" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
            </label>
        </div>

        <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;margin-bottom:.75rem;">
            Telefon
            <input type="tel" name="customer_phone" maxlength="40" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
        </label>

        <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;margin-bottom:.75rem;">
            Skąd (adres odbioru)
            <input type="text" name="pickup_address" required maxlength="255" placeholder="np. ul. Krakowska 10, 00-001 Warszawa" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
        </label>

        <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;margin-bottom:.75rem;">
            Dokąd (adres dostarczenia)
            <input type="text" name="dropoff_address" required maxlength="255" placeholder="np. Stajnia w Kraków" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
        </label>

        <div style="display:grid;gap:.75rem;grid-template-columns:1fr 1fr 1fr;margin-bottom:.75rem;">
            <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;">
                Data
                <input type="date" name="preferred_date" required style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;">
                Godzina
                <input type="time" name="preferred_time" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
            </label>
            <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;">
                Liczba koni
                <input type="number" name="horse_count" required min="1" max="15" value="1" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;">
            </label>
        </div>

        <label style="display:flex;flex-direction:column;gap:.25rem;font-size:.82rem;font-weight:600;margin-bottom:.75rem;">
            Uwagi
            <textarea name="notes" maxlength="2000" rows="3" placeholder="Dodatkowe informacje (np. wymagania dotyczące pojazdu, charakter koni)" style="padding:.55rem .75rem;border:1px solid #d4cdb8;border-radius:6px;font:inherit;width:100%;resize:vertical;"></textarea>
        </label>

        <label style="display:flex;gap:.5rem;align-items:flex-start;font-size:.82rem;margin-bottom:1rem;">
            <input type="checkbox" name="terms" required value="1">
            <span>Akceptuję regulamin marketplace i wyrażam zgodę na przekazanie danych przewoźnikowi.</span>
        </label>

        <button id="tk-inquiry-submit" type="submit" style="width:100%;padding:.85rem 1.2rem;background:{{ $brandColor }};color:#fff;border:0;border-radius:8px;font-weight:700;font-size:1rem;cursor:pointer;">
            Wyślij zapytanie
        </button>
    </form>

    <p style="margin:1rem 0 0;font-size:.72rem;color:#9ca3af;line-height:1.5;font-style:italic;">
        Marketplace transportowy <a href="https://app.hovera.app/t/{{ $tenantSlug }}" target="_blank" rel="noopener" style="color:#9ca3af;">Hovera</a> — pośrednik. Umowa transportu zawierana jest bezpośrednio między Tobą a {{ $companyName }}.
    </p>
</div>

<script>
(function() {
    var form = document.getElementById('tk-inquiry-form-element');
    var banner = document.getElementById('tk-inquiry-banner');
    var submitBtn = document.getElementById('tk-inquiry-submit');
    var apiUrl = '{{ $apiUrl }}';
    var apiToken = '{{ $apiToken }}';

    function showBanner(state, message) {
        banner.style.display = 'block';
        if (state === 'success') {
            banner.style.background = '#dcfce7';
            banner.style.color = '#166534';
            banner.style.border = '1px solid #86efac';
        } else {
            banner.style.background = '#fef2f2';
            banner.style.color = '#991b1b';
            banner.style.border = '1px solid #fecaca';
        }
        banner.textContent = message;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        banner.style.display = 'none';

        var originalLabel = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Wysyłam...';

        var data = {};
        new FormData(form).forEach(function(v, k) { data[k] = v; });

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Hovera-Embed-Token': apiToken
            },
            body: JSON.stringify(data),
            credentials: 'omit'
        }).then(function(res) {
            return res.json().then(function(body) { return { status: res.status, body: body }; });
        }).then(function(r) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel;

            if (r.status === 200) {
                showBanner('success', 'Zapytanie wysłane. Odezwiemy się wkrótce.');
                form.reset();
            } else if (r.status === 422) {
                var errs = r.body.errors || {};
                var msgs = [];
                for (var k in errs) {
                    if (Array.isArray(errs[k])) msgs.push(errs[k][0]);
                }
                showBanner('error', msgs.length > 0 ? msgs.join(' / ') : 'Sprawdź wypełnione pola.');
            } else if (r.status === 429) {
                showBanner('error', 'Za dużo zapytań — spróbuj ponownie za chwilę.');
            } else if (r.status === 403) {
                showBanner('error', 'Ten formularz nie jest dopuszczony do wysyłki z tej strony.');
            } else {
                showBanner('error', 'Nie udało się wysłać zapytania. Spróbuj ponownie.');
            }
        }).catch(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel;
            showBanner('error', 'Brak połączenia. Spróbuj ponownie.');
        });
    });
})();
</script>
