# hovera — Stable employee guide

> Welcome. This guide is for the **Trainer / Employee / Manager / View-only** roles in the stable panel `/app`. The full owner guide lives at `/app/help` from an owner / admin account.

---

## 1. Logging in

1. Open `https://app.hovera.app/app/login`.
2. E-mail and password — your invitation arrived from the stable; the first click sets your own password.
3. After login you land on the start page appropriate for your role.

### Password reset

`https://app.hovera.app/forgot-password` or the "Forgot password" button on the login screen. Link valid for **60 minutes**.

### 2FA (optional)

User menu (top-right) → **"Two-factor authentication"** → scan the QR code with a TOTP app (Google Authenticator / 1Password / Authy). Save the recovery codes.

---

## 2. Roles in the stable

Depending on the role, you'll see different menu items and actions:

| Role | What you see | What you can do |
|---|---|---|
| **Manager** | Everything except stable settings and employees | Manage calendar, invoices, clients, horses |
| **Trainer** | Calendar, your bookings, your horses, clients | Edit your bookings, log activities for horses you train |
| **Employee** | Horse profile (activity log), calendar read-only | Log grooming, feeding, paddock time |
| **View-only** | Everything readable | No changes |

> If you're a **farrier / vet**, you have a separate guide (role `vet` → see specialist guide).

---

## 3. Daily flow — Trainer

### 3.1 Day calendar

**Path:** `/app/calendar` (day / week, grouped by arena / instructor).

- Click an entry → opens details (participants, horse, status).
- Empty slot → click, add new booking (if your role permits).

### 3.2 Booking list

**Path:** `/app/calendar-entries` — full table. Filters: type, status, "mine only", "upcoming".

Statuses: *Requested* → *Confirmed* → *Completed* / *Cancelled* / *No-show*.

After the lesson you mark the status:
- **Completed** — they attended,
- **No-show** — client didn't turn up without cancelling,
- **Cancelled** — client cancelled (with reason).

### 3.3 Your horses

**Path:** `/app/horses`. By default filtered to "Mine" (those you train).

Horse profile → **Activities** tab:
- ➕ **"Add activity"** → type (grooming / feeding / paddock / other), note.

> The last 7 days of activities are visible to the owner in the client portal.

---

## 4. Daily flow — Employee

Your main section: **Horse profile → Activities**.

You log daily horse-care:

- **Feeding** — e.g. "6:00 – hay + oats",
- **Grooming** — duration + notes,
- **Paddock** — which paddock, how many hours,
- **Other** — e.g. "Lost a shoe, call the farrier".

Entries land in the horse timeline immediately. The stable and owner see them.

> If you spot something concerning (lameness, cough) — add an entry **and** send a message via the horse profile → **Messages** tab. The stable gets an e-mail notification.

---

## 5. Daily flow — Manager

Manager has the broadest access except system settings:

- **Calendar + bookings** — full management, can move others' entries,
- **Clients** — add, edit, generate magic links to the portal,
- **Horses** — add, edit, documents, health,
- **Invoices** — issue, send by e-mail, mark as paid,
- **Passes** — sell, cancel, edit expiry.

Full description of each module is in the owner guide (`/app/help` from an owner/admin account).

---

## 6. Messages and notifications

### 6.1 Messages on the horse profile

Each horse has a chat between the stable and the owner. You can:
- read history,
- write a message (e.g. "Paddocked for 3h today" — but you can also do this via Activities),
- attach files (PDF/JPG/PNG, max 10 MB each, up to 5 files).

### 6.2 E-mail notifications

By default you get an e-mail on:
- a new booking with you (Trainer),
- a client reply to your message,
- a stable notification ("Farrier coming tomorrow at 14:00").

User menu → **"Notifications"** lets you disable individual categories.

---

## 7. Interface language

User menu (top-right) → **Polski / English / Deutsch / Français**. The preference is stored per user — persists on next login.

> Clients see the portal in the language set by the stable (or their own, if available). Your switch doesn't affect what they see.

---

## 8. Security

- **Password** — min. 8 chars; don't reuse from other services.
- **Reset** — `/forgot-password` (mail with link, TTL 60 min).
- **2FA** — strongly recommended for Manager / Trainer roles.
- **Logout** — user menu → "Logout"; session also expires after 8h of inactivity.

> **Never share your password with co-workers.** Everyone has their own account — important for the audit log (who entered the wrong invoice, who logged the activity).

---

## 9. Common issues

| Problem | What to do |
|---|---|
| Forgot password | `/forgot-password` → mail with link |
| Reset mail not arriving | Check spam; ask the stable owner |
| Can't see a booking | Check "Mine only" filter — uncheck if needed |
| "No permission" when editing | Your role doesn't allow it — ask admin to change |
| Client says they didn't get an e-mail | Check address on client record; try "Resend" |

---

## 9a. New modules relevant for you

- **Horse feeding plan** — the "Feeding plan" tab on the horse profile says exactly what to feed and when. Trainer/Manager edits; Employee reads and executes. Owner also sees this in the client portal (diet transparency).
- **Feed inventory** — `/app/feed-inventory`. Daily issuance: click **"+ Stock movement"** → "Consumption" → amount → confirm. Stock auto-decreases. Below-threshold items show a sidebar badge counter.
- **Horse weight log** — Trainer/Employee logs monthly weight in the **Weight** tab (kg + optional heart girth). "Change" column flags 🟢 gain / 🟡 loss / ⚪ stable.
- **Photo gallery** — **Gallery** tab with thumbnails; the owner sees a grid in the portal.
- **"Today" dashboard** (`/app`) — 4 KPI tiles up top + today's bookings table. Quick view of what's on today.

---

## 9b. What you see in the panel — your role

The sidebar is filtered. Visible sections depend on role:

| | Trainer | Employee | Manager | View-only |
|---|:-:|:-:|:-:|:-:|
| Horses · Care & health · Day plan · Bookings | ✓ | ✓ | ✓ | ✓ |
| Clients | ✓ | — | ✓ | ✓ |
| Boxes · Pricing · Recurring · Instructors · Arenas | ✓ | — | ✓ | ✓ |
| Feed inventory | — | ✓ | ✓ | ✓ |
| Specialists · Treatment templates | — | — | ✓ | ✓ |
| Invoices · Passes · Reports | — | — | ✓ | ✓ |
| Bulk invoicing | — | — | ✓ | — |
| Settings · Team | — | — | — | — |

> Missing sections in the sidebar isn't a bug — your role doesn't need them. If you must change something in a locked section, ask the admin/manager.

---

## 10. Support

- **Your stable** — owner / admin (contact on the stable record),
- **hovera (technical)** — `support@hovera.app`.

---

*Documentation is updated as new features ship. Version in the panel footer.*

Other roles: **owner / admin** guide (`/app/help` from owner/admin account), **specialist** guide (`/app/help` from a vet account) and **client portal** (`/s/{slug}/portal/help`).
