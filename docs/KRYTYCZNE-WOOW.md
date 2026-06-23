# Hovera — Krytyczne „WOOW" (audyt po sesji O5 messaging)

> Data: 2026-06-23
> Kontekst: audyt względem `docs/WOW-PLAN-V2.md` (z 2026-06-20) PO domknięciu epików O5
> (Channel B/C/D messaging, KSeF submit, panel specjalisty).

## TL;DR

Produkt jest **feature-complete**. ~90% „wow planu v2" jest już wdrożone.
„Wow" nie leży już w nowych dużych modułach — leży w **domknięciu pętli pieniądza**
oraz w **polishu real-time / mobile**.

---

## 1. Reality check — co z „wow planu" jest już zrobione

| Pozycja z planu (jako „gap") | Status dziś |
|---|---|
| KSeF submit + UPO | ✅ (#449–454) |
| Owner↔stable / specjalista / team messaging | ✅ Channel A/B/C/D |
| Panel specjalisty + magic-link login | ✅ |
| PDF faktur (barryvdh/laravel-dompdf) | ✅ |
| Calculator `extra_horse_fee` / `horses_count` | ✅ |
| Batch-complete health records | ✅ `batch_register_completion` |
| Reports panel (4 raporty) | ✅ Revenue / ReceivablesAging / HorseUtilization / InstructorUtilization |
| Leaflet route map w wycenie | ✅ `route-map.blade.php` |
| POI library + marketplace board | ✅ `PoiResource`, `TransportMarketplaceController` |
| Push FCM (kreait/laravel-firebase) | ✅ podpięty do 6+ notyfikacji |
| PWA (manifest.json + sw.js) | ✅ |
| Webhook outbound (Slack/Zapier-ready) | ✅ `WebhookSubscription` + `DeliverWebhookJob` |
| Owner notifications hub | ✅ `Owner/Resources/NotificationResource` |
| Bulk-monthly boarding FV | ✅ `GenerateMonthlyBoardingInvoicesJob` |

---

## 2. Realnie zostało — prawdziwe dźwignie „wow"

### 🥇 1. Owner 1-click pay (BLIK) w panelu — NAJWAŻNIEJSZE
- **Dziura w pętli pieniądza.** Providery (PayU / P24 / Stripe) i faktury w panelu ownera
  istnieją, ale owner widzi FV **read-only** — nie zapłaci jednym tapem.
- Najsilniejszy wow + realna konwersja na cash. Konkurencja (Equilab / RABO) tego nie ma w PL.
- Effort: ~2 dni.

### 🥈 2. Real-time / mobile polish
- Messaging (Channel B/C/D) działa przez modal-akcję, nie live.
- Live refresh wątków + `@mention` autocomplete dropdown + PWA install-prompt per panel.
- Zamienia „CRUD" w „nowoczesną apkę".
- Effort: ~2–3 dni.

### 🥉 3. SMS dla critical events (SmsApi.pl)
- Jedyny brakujący kanał (mail + push + database już są).
- Booking confirmed / payment overdue SMS-em = duży perceived value w PL.
- Effort: ~1–2 dni.

### 4. Newsletter do klientów stajni (TipTap + queue)
- Narzędzie marketingowe, niezrobione.
- Effort: ~2 dni.

### 5. Google Calendar 2-way sync
- Dziś tylko jednostronny eksport ICS (`IcsCalendarBuilder`).
- Effort: ~3–4 dni.

---

## 3. Delight micro-touche (tanie, duży efekt)

- Confetti 🎉 przy 100% completed bookings dnia.
- „Najlepszy klient miesiąca" widget na dashboardzie.
- Anniversary: „Twój koń jest u nas 4 lata 3 miesiące".
- Quick-share publicznej karty konia (link → FB/IG).

---

## 4. Rekomendacja

Jedna rzecz na maksymalny efekt „wow" + biznes: **owner 1-click BLIK pay**.
Domyka jedyną brakującą część przepływu pieniądza, jest natychmiast odczuwalny i
różnicuje vs konkurencja w PL.

**Następny krok:** spike — przegląd istniejącej integracji PayU/P24/Stripe i najkrótsza
droga do akcji „Zapłać tę FV" w panelu ownera.

---

## 5. Świadomie pomijamy (carry-over)

❌ AI/ML w jakiejkolwiek formie · ❌ program poleceń · ❌ życzenia urodzinowe ·
❌ achievements/badges (gimmick bez biznesowej korzyści).
