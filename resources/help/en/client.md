# hovera — Client portal guide

> Welcome to the client portal. This is where you'll find all your bookings, passes, invoices and information about your horses. The stable that uses hovera hosts the portal at `https://app.hovera.app/s/{stable-slug}/portal`.

---

## 1. Logging in (magic link)

The portal **does not use passwords**. You log in with a one-shot link sent to your e-mail:

1. Open the portal login page — the address is given to you by the stable (e.g. `https://app.hovera.app/s/pegasus-stables/portal/login`).
2. Type the e-mail address (the same one the stable has on your client record).
3. Click **"Send link"**.
4. Check your inbox — within a few seconds you'll get a message with the link.
5. Click the link → you're logged in for **30 days**.

> **The link is one-shot and valid for 30 minutes.** If you miss it, simply request a new one.

### Not receiving the e-mail — what now?

- Check **Spam / Promotions / Updates** folder.
- Make sure the address is exactly the one the stable has (a typo = no e-mail).
- Contact the stable — they can copy the link from the panel and send it via SMS.

---

## 2. Dashboard

After logging in you'll see a single screen with all sections. Each section appears only if you have data there.

### 2.1 Upcoming bookings

A list of your bookings from today onwards, sorted by closest first.

Each item shows:
- **date and time** of start + lesson length,
- **status** (Requested / Confirmed),
- **instructor**, **horse**, **arena**,
- action buttons.

#### Actions

- **Reschedule** — opens the rescheduling screen (only for *Confirmed* status). We'll show the next available slots with the same instructor; pick one = a request is sent to the stable and you receive an e-mail.
- **Cancel** — opens a secure cancellation form (signed link, valid until the booking starts).

> **Important:** "Reschedule" and "Cancel" are only available for bookings that have not yet started.

### 2.2 Your passes

If the stable sells passes (e.g. "10 lessons"), you'll see active ones here. Each pass shows:

- remaining uses (e.g. **7 / 10 remaining**),
- progress bar,
- expiry date,
- status (Active / Exhausted / Expired).

The **"Recently used"** section shows the last 5 lessons charged to the pass.

### 2.3 Booking history

Lessons that already happened or were cancelled / no-show. Statuses:

- **Completed** — lesson took place,
- **Cancelled** — you or the stable cancelled,
- **No-show** — you didn't turn up without cancelling.

### 2.4 Unpaid invoices

If the stable issued you invoices and they are unpaid, they appear here with:

- document number,
- issue date + due date (red text if overdue),
- amount due.

Clicking a row opens the public invoice view (signed URL — no login) with a **"Pay now"** button if the stable has a payment gateway configured (Przelewy24 / PayU / Stripe / Mollie).

### 2.5 Messages

5 most recent messages from horse-related conversations. Full list: **"All →"** link in the section header.

### 2.6 Your horses

Horses you own at this stable. Each row shows:

- name, breed, age,
- **health badges**:
  - 🔴 **X overdue** — X past-due care items (vaccinations, farrier, dentist) — **action required**,
  - 🟢 **X within 30 days** — X items scheduled this month,
  - ⚪ **OK** — everything up to date.

When you have unread messages you'll see a **📬 X new messages badge**.

Clicking a row → horse profile (section 3).

---

## 3. Horse profile

Clicking a horse from the dashboard opens its full profile. Sections:

### 3.1 Basics

- name, breed, coat colour, date of birth, sex,
- microchip, passport number, UELN,
- current box.

### 3.2 Care & health (timeline)

Vaccinations, farrier visits, dental:

- 🔴 **overdue** — date passed, book a visit,
- 🟡 **upcoming in 30 days** — plan ahead,
- 🟢 **up to date** — next visit > 30 days away.

Each item shows the date of the last visit + suggested next date.

### 3.3 Activities

Grooming, exercise, paddock time — entries from the last 7 days added by the stable.

### 3.4 Messages

Chat with the stable about this horse. You can:

- read message history (from the stable and from you),
- send a new message (e.g. "Please groom him before today's lesson"),
- attach **up to 5 files** (PDF/JPG/PNG, **max 10 MB each**).

The stable gets an e-mail notification; you also get one for their replies.

### 3.5 Documents

Passport, contract, insurance, certificates — PDF/JPG files (max 25 MB).

Actions:
- **Download** any document,
- **Upload** a new document (passport, insurance…),
- **Delete** documents you uploaded yourself (stable-uploaded ones cannot be deleted).

---

## 4. Rescheduling a booking

Click **"Reschedule"** on an upcoming booking → opens a screen with:

- the current date/time,
- a list of **3-7 nearest free slots** with the same instructor.

Pick the preferred slot → click **"Send request"**:

1. The stable receives a notification,
2. You receive a confirmation e-mail,
3. The booking is moved (status remains *Confirmed*).

> If no slot fits, message the stable via the **Messages** section in the horse profile or by direct e-mail.

---

## 5. Cancelling a booking

Click **"Cancel"** → opens a signed URL (cryptographically signed link, valid only until the lesson starts).

The form shows:
- booking details,
- **"Cancellation reason"** field (optional, but useful to the stable).

Click **"Confirm cancellation"** → status becomes *Cancelled*, the stable is notified.

> Cancellation well in advance usually returns the entry to your pass. Cancellation just before the lesson may incur a fee — terms are set by the stable.

---

## 6. Invoices

Clicking an invoice from the dashboard opens its public view:

- full data (seller, buyer, line items, totals, VAT),
- **"Download PDF"** button,
- **"Pay now"** button (if the stable has a gateway).

### Online payment

Click **"Pay now"** → redirect to the gateway (BLIK / card / transfer). After paying you return to the portal — invoice status is updated automatically once the webhook confirms.

> If you pay by traditional bank transfer, use the account number and reference from the invoice — the stable will mark it paid manually after reconciliation.

---

## 7. Messages — full list

The **"All →"** section opens a screen with all threads about your horses. Filters: horse, unread.

Clicking a thread takes you to the horse profile, Messages section.

---

## 8. Portal language

The portal speaks four languages: **Polish / English / German / French**. By default it uses the stable's language; if you switch it, the preference is stored in your session.

> There's no language switcher inside the portal (it's in the staff panel); if you want a different language, ask the stable to change the default.

---

## 9. Security & privacy

- **Magic-link login** = no password to remember, no password to leak.
- **30-day session** — after that you enter your e-mail again.
- **Logout** — top-right button.
- **Your data** — you only see *your* bookings, *your* horses, *your* invoices. Even if the stable has 100 clients, each only sees their own.

> The portal only shows data from this one stable. If you ride at several — each has its own portal URL.

---

## 10. Common issues

| Problem | What to do |
|---|---|
| Not receiving the e-mail with the link | Check spam; then ask the stable to send the link manually |
| Link expired / doesn't work | Type your e-mail again — we'll send a fresh one |
| "Reschedule" shows no slots | The instructor may be unavailable — message the stable |
| "X overdue" badge won't go away | The stable must mark the farrier/vaccination visit as done |
| I don't see an invoice | Contact the stable — they may need to re-issue |
| Attachments >10 MB won't upload | Compress the photo / split the PDF into parts |

---

## 11. Support

- **Stable** — e-mail / phone visible on the public stable page `https://app.hovera.app/s/{stable-slug}`,
- **hovera (technical)** — `support@hovera.app`.

---

*Documentation is updated as new portal features ship. The system version is shown in the footer of the stable admin panel.*
