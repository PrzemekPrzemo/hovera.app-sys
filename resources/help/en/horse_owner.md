# Welcome, Horse Owner

Hovera connects you with stables, vets, farriers and horse transporters
across Poland. Your account is **free forever** — paid for by the
stables and transport companies you do business with.

This guide walks you through first steps: adding a horse, ordering
transport, and accepting an offer.

## What you get free

- **Profile for every horse** — passport, breed, weight, allergies,
  attending vet, farrier, insurance. All in one place, accessible from
  your phone in 2 seconds.
- **Care history** — vaccinations, shoeings, vet visits, receipts. The
  stable where the horse is kept can see this too (with your consent)
  and update it after visits.
- **Photo and document gallery** — passports, certificates, transport
  pictures, breeding documents. Stored encrypted; only you can access.
- **Transport orders** — compare offers from verified carriers in your
  region. Automatic pricing (distance + fuel + VAT), offers usually
  appear within 24 hours.
- **Stable invoice history** — receive boarding invoice PDFs directly
  in your panel. No emails to dig through.

## First steps

### 1. Add your horse

`My horses → Add horse`. Required:

- **Barn name** (one name is enough — you can add registered / pedigree
  name later)
- **Breed** — from a list (PSK, KAJ, Hucul, Małopolska, Wielkopolska,
  Trakehner, AA, Welsh, Hanoverian, etc. — missing? Use "Other" and let
  us know)
- **Date of birth** (approximate is OK)
- **Sex** — mare / gelding / stallion
- **Passport number** — from your PZHK (Polish Horse Breeders
  Association) passport or another national registry

Optional but **highly recommended**:

- **Microchip number** (15 digits) — Hovera uses it to cross-check the
  passport on international transports
- **Profile photo** — helps carriers identify the horse at pickup
- **Health notes** — e.g. "bran allergy", "won't enter water on grass",
  "won't load without a companion"
- **Attending vet** + phone — useful in emergencies

### 2. Connect your horse to a stable (optional)

If your horse boards at a Hovera-using stable → `My horses → [horse] →
Link to stable`. Enter the stable name or slug
(`stable.hovera.app/...` from your contract). The stable gets notified
and approves (or rejects). Once approved:

- The stable sees your horse in their panel (for adding vet visits,
  shoeings, lessons)
- You retain full control of the profile
- You receive boarding invoices from the stable **automatically** in
  `My invoices`. Click "Pay" if the stable has a gateway
  (P24 / PayU / Stripe) or use the bank transfer details if not.

If the stable **isn't** on Hovera — your horse stays "unlinked". That's
fine; you can still order transport and keep a private history.

## Ordering transport

### Step 1: new inquiry

`Order transport`. You fill in:

- **From / to** — full addresses (postal codes increase pricing accuracy)
- **Preferred date** + optional time
- **Horse** — pick from dropdown (or "unassigned" — describe in notes
  which horse)
- **Mode** — *one-way* (cheapest), *round-trip* (carrier returns with
  you), *carrier-return-empty* (carrier drives back without load, you
  only pay the outbound leg)
- **Notes for the carrier** — special needs, gate hours, contact at
  destination, clinic opening hours, etc.

### Step 2: favourite carriers (optional)

By default the inquiry goes to **all** verified carriers in your region
(broadcast — more offers, sometimes 5–8).

If you want a tighter circle — go to `Favourite carriers` and add 2–5
trusted companies. On a new inquiry, check "Send ONLY to favourites" —
the request goes only to them. Fewer offers, only from vetted carriers.

### Step 3: favourite routes (time saver)

Often transport the same horse on the same route? After filling the
addresses, click "Save as favourite route", give it a name ("Stable →
Janów clinic"). Next time you pick from the dropdown and everything
(addresses, notes) auto-fills.

### Step 4: compare offers

In `My orders` you see all carrier responses. Each offer shows: net +
VAT + gross price, validity (typically 7 days), additional terms (e.g.
"price includes 2 horse-hours of waiting").

**Click "Accept" → ONE offer wins.** The rest auto-transition to
"Withdrawn" — those carriers get a "lead closed" notification.

### Step 5: acceptance + invoice

The acceptance link arrives by email (and works **without login** — you
can forward it to your partner to sign).

On the acceptance page you choose the **invoice recipient**:

- **Individual** (default) — invoice issued in your name
- **Company** — enter VAT ID, company name, address. For Polish VAT IDs
  Hovera auto-fetches the rest from GUS (click "Fetch from GUS"), for
  EU VAT IDs (e.g. DE123456789) it verifies via VIES.

After acceptance the carrier issues the invoice via KSeF (Polish
national e-invoicing system — mandatory from 2026). You get the PDF by
email.

## Sport results (LiveJumping)

If your horse competes in jumping, dressage, eventing — soon integration
with `livejumping.com` is available (stable-side). The stable can
display your horse's results in their profile. Requires the stable's
partnership with LiveJumping (master Hovera admin enables/disables it
globally).

## Security

- **Your horses are visible only to you** — Hovera does not share the
  list with any third party. A stable you've linked sees only the
  horses linked to it (not your full list).
- **Transport — carriers see only the specific inquiry** (addresses,
  date, optionally the horse name — no profile/photos). After
  acceptance the winning carrier gets a phone number plus the notes
  you've agreed to share.
- **Hovera does NOT carry out the transports** — it's only a
  marketplace. The carriage contract is **directly with the carrier**.
  Hovera takes no commission and is not liable for performance.
- **Invoices** — issued by the stable / carrier under their own VAT ID.
  Hovera doesn't appear on the invoice (except its SaaS invoice to the
  stable/carrier, which you don't see).

## FAQ

**Can I delete a horse?**
Yes — `My horses → [horse] → actions → Delete`. You can restore it
within 30 days from the "Archive" section. After 30 days it's purged,
but invoices already issued (KSeF) are retained for the legally
required period (5–10 years).

**What if the horse changes owner?**
Export the horse profile (`My horses → [horse] → Export JSON+PDF`),
send it to the new owner. They create a fresh Hovera account and
import the profile. Your historical data (boarding invoices) stays
with you.

**Can I add multiple horses?**
Yes, unlimited. The FREE account covers an unlimited number of horses.

**Can I invite family / a partner?**
In MVP — no co-ownership yet (multiple users for one horse). Workaround:
create one account with a shared email and password. Full
multi-user — in the post-MVP roadmap.

**Can I have both a stable account and an owner account?**
Yes — multi-tenancy. Create a second tenant from `Account → Add
company`, choose type `Stable`. The tenant switcher (top of screen)
flips between them.

## Support

- **Support email:** `support@hovera.app` (response within 1 business day)
- **Help centre:** `/help/horse_owner` (publicly accessible)
- **Bug reporter in panel:** bottom-right corner — report flows to us
  with metadata (browser, URL); nothing to copy manually
