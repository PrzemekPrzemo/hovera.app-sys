# hovera — Stable Owner Manual

> Welcome to hovera. This guide walks you through every feature of the stable owner panel. Most steps start from the main navigation in `/app`.

---

## 1. First steps

After signing in, you land on `/app`. Left-side navigation has groups:

- **Stable** — horses, clients, boxes, buildings, specialists, passes, care, boarding pricing
- **Calendar** — today's plan, bookings, recurring sessions, instructors, arenas
- **Finances** — invoices
- **Settings** — stable settings, invoicing, payments, KSeF, team members

Recommended order:

1. **Stable settings** — fill in company info, branding, working hours
2. **Buildings** — create at least one ("Main barn")
3. **Boxes** — assign each to a building
4. **Boarding pricing** — define services (hay, box cleaning, transport)
5. **Instructors + Arenas** — so the calendar can accept bookings

---

## 2. Stable settings

**Path:** `/app/tenant-settings`

Form sections:

- **Identification** — name, legal name, tax ID
- **Location** — country, default language, timezone, currency
- **Branding** — primary color, logo, hero image (used on public page)
- **Public page `/s/{slug}`** — tagline, opening hours, description, contact, social media
- **Online booking** — whether clients can book via the public page; lesson length, working hours, lead time

**Example:**

| Field | Value |
|---|---|
| Name | "Pod Lipami" Stable |
| Tagline | Boarding, lessons, recreation since 1995 |
| Opening hours | Mon–Fri 9:00–20:00 · Sat–Sun 8:00–18:00 |
| Min. lead time | 4 hours |
| Max horizon | 30 days |

---

## 3. Horses

**Path:** `/app/horses`

### Adding a horse

Click **"Create horse"** → fill in:

- **Name** (e.g. "Bucephalus")
- **Owner** (Select from clients; empty = stable owns)
- **Box** (Select from active boxes)
- **Microchip / Passport no. / UELN** (Universal Equine Life Number)
- **Sex:** Mare / Gelding / Stallion / Breeding stallion
- **Breed, color, birth date**
- **Boarding services** (multi-select from pricing)

After save, horse appears in the list. Columns: Name, Breed, Sex, Color, Age, Owner.

### Horse card (tabs)

Edit horse → 4 tabs:

- **Care & health** — vaccinations, farrier, dentist (with auto-suggested next due)
- **Activities** — feeding, grooming, turnout
- **Messages** — chat with owner (mail-out)
- **Documents** — passport, contract, insurance (PDF/JPG, up to 25 MB)

---

## 4. Boxes and buildings

### Buildings — `/app/buildings`

One stable (as a place) can have multiple buildings: "Red barn", "New stable", "Paddock pavilion". Each is a group of boxes.

**Fields:** name, sort order, active.

### Boxes — `/app/boxes`

Form: building, name/number, short code, type (indoor / paddock / outdoor / quarantine), size (m²), monthly boarding rate, active.

Table columns:
- **Building** (with grouping — collapse "Red barn")
- **Name, type, m²**
- **Status** — Free (green) / Occupied (gray)
- **Horse sex** (if assigned)
- **Boarding (PLN/mo.)**

**Example structure:**

```
Building "Red barn"
  ├ Box 1 — indoor — Free
  ├ Box 2 — indoor — Occupied (Mare "Lucy")
  └ Box 3 — indoor — Occupied (Gelding "Bucephalus")

Building "East paddock"
  ├ Pad 1 — paddock — Free
  └ Pad 2 — paddock — Occupied (Breeding stallion "Titan")
```

---

## 5. Clients

**Path:** `/app/clients`

### Adding a client

- **Type:** individual / family / company
- **Full name / company name**
- **Email, phone**
- **Tax ID** (with "Fetch from GUS" if Polish GUS API is configured)
- **Horse owner identification (ARMiR):**
  - **EP no.** — producer ID assigned by ARMiR when registering a horse in the Polish Equine Central Database
  - **PESEL** — fallback if owner has no EP

### Client card

When editing:

- All form fields
- **"Horses" tab** — list of horses owned by this client
- Action **"Copy portal link"** — generates a magic link (TTL 30 min) for manual copy
- Action **"Email portal link"** — sends sign-in email

---

## 6. Calendar

### Today's plan — `/app/calendar`

Day view grouped by instructor/arena. Click an empty slot to add a booking. Click an entry to edit/delete.

### Bookings — `/app/calendar-entries`

Full bookings table. Filters: type, status, horse, instructor, "upcoming only".

**Statuses:** Requested → Confirmed → Completed / Cancelled / No-show.

**Booking types:** Individual lesson, Group lesson, Training, Care (vet/farrier), Event, Block.

### Recurring sessions — `/app/recurring-calendar-entries`

Creates a series (e.g. "School Mon 17:00, weekly, until year end"). Action **"Generate occurrences"** expands the series into individual bookings (max 365 at once).

---

## 7. Specialists (farriers + vets)

**Path:** `/app/specialists`

List of external contractors and stable employees who shoe / treat horses.

### Adding

- **Specialty:** Vet / Farrier
- Full name, email, phone, calendar color
- **Hovera account (optional)** — if specialist is staff (TenantMembership), link them to a User. The signed-in employee then gets a **"My tasks"** view listing their procedures.

### Linking with health records

When creating an HR (`/app/health-records`) pick type:
- "Farrier" → specialist list filters to `farrier`
- other types → list filters to `vet`

In the HR table, "Performed by" column shows the specialist's name.

---

## 8. Team members

**Path:** `/app/team-members` (visible only to owner / admin)

### Adding

Click **"Add team member"** → fill:
- email, name, role

System auto:
- if user exists → creates `TenantMembership`
- if not → sends `UserInvitation` (activation email, TTL 7 days)

### Roles

| Role | Description |
|---|---|
| **Owner** | Full access |
| **Admin** | Same minus stable deletion |
| **Manager** | Manages calendar, invoices |
| **Instructor** | Calendar, bookings, own horses |
| **Employee** | Activity log (feeding, cleaning) |
| **Vet** | Linked to a Specialist + My tasks |
| **View only** | Read-only |

### Password reset

Action **"Send password reset link"** (key icon next to active employee) — emails a link to `/app/password-reset/request`. Expires in 60 minutes.

---

## 9. Invoices

### Configuration — `/app/invoicing-settings`

- **Numbering FV / Proforma / Correction** — pattern with `{seq}`, `{YYYY}`, `{MM}`, `{prefix}` placeholders
- **Numbering reset** — Yearly / Monthly / Never
- **Default due in days** — e.g. 7
- **Seller details** (snapshot on invoice)

### Issuing — `/app/invoices`

1. Click **"Create"** → pick client (auto-fills buyer)
2. Add line items (Repeater): name, qty, unit, net price, VAT
3. Save → status `Draft` → click **"Issue"** → number + date
4. Click **"Email"** → client receives link to public view (with "Pay now" if you have an online gateway)
5. Click **"Send to KSeF"** (if configured) → certificate signing + send

### Corrections

Click **"Correction"** on a posted invoice → creates a correction draft with zero items; fill in "after change" quantities → issue.

---

## 10. Online payments — `/app/payment-settings`

Pick default gateway:

- **None** — everything offline (transfer, cash)
- **Przelewy24** — Merchant ID + POS ID + CRC + API key
- **PayU** — POS ID + OAuth credentials
- **Stripe** — secret_key + webhook_secret
- **Mollie** — API key

Once configured, every issued invoice email has a **"Pay now"** button.

---

## 11. KSeF (Polish e-invoicing) — `/app/ksef-settings`

Required:
- **Stable tax ID** (KSeF context)
- **Environment:** test / demo / prod
- **Certificate** — PFX (.pfx) or PEM (.crt + .key); key & password encrypted via Laravel Crypt + AES-256

After upload, each issued invoice has a **"Send to KSeF"** action — certificate signing + XML send.

---

## 12. Public site and embed widgets

### Public page — `https://app.hovera.app/s/{slug}`

Renders automatically based on the "Public page" section in `/app/tenant-settings`. Primary color, logo, hero image, description, opening hours, contact, social media. Optional: instructor list, boarding pricing, box availability.

### Embed widgets

In `/app/tenant-settings` → "Widgets" section — ready iframes to paste into Wordpress / Squarespace / own website:

- **Vacant boxes** ("X vacant boxes")
- **Book online** (CTA to booking flow)
- **Boarding pricing** (table)
- **Instructor list**

---

## 13. Client portal

Client signs in via `https://app.hovera.app/s/{slug}/portal/login`:
- enters email
- gets magic link by mail (TTL 30 min)
- click → lands on dashboard

From **`/app/clients/{id}`** you can generate the link manually (for SMS/Messenger) or send by mail.

In the portal, client sees:
- upcoming bookings (with "Reschedule" / "Cancel")
- their passes (X / Y remaining)
- booking history
- unpaid invoices
- their horses (with health alerts)
- messages

---

## 14. Tips

- **Keyboard shortcuts** — `?` in the panel shows the list
- **Language** — switch in user menu (PL / EN / DE / FR); preference saved per user
- **Support** — support@hovera.app

---

*System version: see panel footer. This documentation is updated alongside new modules.*
