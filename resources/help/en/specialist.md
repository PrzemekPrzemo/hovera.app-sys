# hovera — Specialist (farrier / vet) guide

> Welcome. This guide is for the **Specialist** role — i.e. a farrier or vet with a hovera account (TenantMembership with `vet` role). Panel access: `https://app.hovera.app/app`.

---

## 1. Logging in

1. Open `https://app.hovera.app/app/login`.
2. Enter e-mail and password (you set the password on first login — the invitation arrives by e-mail from the stable).
3. After login you land on the panel start page — by default **My tasks**.

> **Password reset:** use the "Forgot password" link or `https://app.hovera.app/forgot-password`. We'll send a link to your e-mail (TTL 60 min).

---

## 2. My tasks

**Path:** `/app` (start page if your role is `vet`).

This is your main view. The table shows:

- **Today** — visits scheduled for today,
- **This week** — next 7 days,
- **Overdue** — past dates not yet marked done.

Each row: date, time, horse, stable (if you work for more than one), visit type (shoeing / vaccination / dental check), status.

### Actions

- **Open** — visit details, horse data, history.
- **Mark as done** — after the visit, click and add a note. This automatically:
  - moves the visit to *Completed*,
  - updates the horse health badge (the 🔴 *X overdue* disappears),
  - suggests the next visit (e.g. shoeing every 6 weeks → proposed slot in calendar).
- **Reschedule** — if you must (equipment failure, illness), pick a new slot → the stable gets a notification.

---

## 3. Calendar

**Path:** `/app/calendar`

Shows all visits (yours and others) at the stable — day / week. Your entries are coloured with your colour (set by the stable in your specialist record).

Filters:
- **Mine only** — shows only entries assigned to you,
- **Type** — farrier / vet / other,
- **Status** — requested / confirmed / completed.

### Adding an entry

You can add a visit yourself (e.g. "I came over for an extra check today") — click an empty slot → form:
- horse (pick from the stable list),
- type (vaccination / shoeing / dentistry / other),
- duration (default 30 min),
- note.

> The stable sees your entry immediately — once approved, they invoice it (if you bill through hovera).

---

## 4. Horse profile

Click a horse in a visit → opens its profile. Sections relevant to you:

### 4.1 Care & health (timeline)

Full history:
- vaccinations (tetanus, flu, EHV),
- shoeing (date, farrier, work description),
- dental visits,
- other vet treatments.

Filters: entry type, date range, author (you / another specialist / stable).

### 4.2 Activities

Grooming, exercise, paddock time — entries from the last 7 days. Useful — you see what the horse did before your visit (e.g. whether it was hard-trained the day before shoeing).

### 4.3 Documents

Passport, insurance, blood tests — you can download and view.

### 4.4 Messages

Chat with the horse owner + stable. You can:
- read previous decisions,
- write a message (e.g. "After yesterday's shoeing leave him without work for 24h"),
- attach photos (PDF/JPG/PNG, max 10 MB each).

---

## 5. Marking a visit as done

This is your most common flow.

1. Open the visit (from **My tasks** or calendar).
2. Click **"Mark as done"**.
3. Fill in:
   - **Actual date** (default today),
   - **Note** (e.g. "Left front fine, right needs observation"),
   - **Next visit** — suggested date (shoeing 6 weeks, vaccination 12 months). Editable.
   - **Cost** (optional) — if the stable bills you through hovera.
4. Confirm.

Auto-side-effects:
- visit transitions to *Completed*,
- horse timeline gets an entry,
- horse health badge refreshes,
- if you set a next visit → a new calendar entry is created (status *Requested*),
- the owner receives an e-mail notification.

---

## 6. Two situations: stable employee vs. external

| Aspect | Employee (TenantMembership) | External |
|---|---|---|
| Login | Yes, account with role `vet` | No — the stable enters visits for you |
| "My tasks" view | Yes | — |
| E-mail notifications | Yes | Yes (if the stable has your e-mail) |
| Can add calendar entries | Yes | No |
| Sees horse history | Yes | No (unless the stable shares documents) |

If you're an **external** specialist (freelance) — most of this guide doesn't apply. The stable contacts you by e-mail / phone and enters the visit themselves.

---

## 7. Multiple stables in one account

If you work at **several stables** in hovera, each invites you separately (separate TenantMembership). In the top-left of the panel you'll see a stable switcher (or visit `/tenant/select`).

After switching you only see data from the selected stable — horses, calendar, tasks.

---

## 8. Interface language

User menu (top-right) → **Polski / English / Deutsch / Français**. The preference is stored per user — persists across logout.

---

## 9. Security

- **Password** — minimum 8 characters; reset via `/forgot-password`.
- **2FA** — optional, enabled in user menu → "Two-factor authentication" (TOTP, e.g. Google Authenticator / 1Password).
- **Session** — expires after 8 hours of inactivity.

---

## 10. Support

- **Stable** — e-mail / phone visible on the stable page,
- **hovera (technical)** — `support@hovera.app`.

---

*Documentation is updated as new features ship. The version is shown in the panel footer.*

Other roles: **owner / admin** guide (`/app/help` from an owner/admin account) and the **client portal** guide (`/s/{slug}/portal/help`).
