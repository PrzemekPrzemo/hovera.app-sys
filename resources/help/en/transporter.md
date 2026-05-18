# Welcome to Hovera Transport

Glad to have you on board. This document walks you through your first
days as a transporter — from account activation to your first accepted
quote.

## How it works

- **Sign-up → document verification → activation → sending quotes.**
  Account creation is instant, but sending quotes to customers is
  blocked until our team verifies your documents (carrier liability
  insurance, transport licence, tax ID, vehicle registration). Usually
  one business day.
- **Hovera is an intermediary marketplace, NOT a transport company.**
  We do not own vehicles, do not employ drivers, do not take liability
  for transport execution. We connect you with customers and provide
  the tools: a management panel, a pricing calculator, quote generator,
  invoicing, a public profile.
- **Transport contracts are directly between you and the end customer.**
  You issue the invoice under your own tax ID, your own numbering,
  through your own KSeF (or local e-invoicing). Hovera is not a party
  to the transport contract.

## First steps after activation

1. **Add your vehicles** in the `Vehicles` section. For each entry:
   name, registration plate, capacity (horses), gross weight, photos
   (3–6 recommended, including interior), equipment (air suspension,
   camera). This data goes onto invoices and onto your public profile.
2. **Add your drivers** in the `Drivers` section. Each driver receives
   notifications about upcoming jobs on their email/phone (from a
   separate SMTP `transport@hovera.app`, so they don't mix with other
   Hovera mail).
3. **Configure service areas** in `Settings → Service areas`
   (multi-select of provinces). Critical — without this you won't
   receive broadcast leads. A lead from Mazovia province only reaches
   transporters whose service area includes Mazovia or an adjacent
   province (hardcoded in `config/transport.php`).
4. **Configure your public profile** (`/t/{your-slug}`). It's your
   marketing landing — indexed by Google, shareable on social, with
   its own OG image for previews. Fill in the description, upload a
   logo, mark service areas, verify your phone and email. The
   "Request a quote" CTA points directly at you (direct mode — the
   lead does not go to anyone else).
5. **Connect a routing API** in `Settings → Routing`. The Solo plan
   includes free OpenRouteService. Pro/Fleet plans let you plug your
   own Mapbox or Google Maps Routes API key for better route quality
   (especially for heavy vehicles — accounts for bridges, restrictions,
   weight limits). After adding the key, click "Test key" — Hovera will
   probe it and tell you if it works.
6. **Issue your first quote.** Open `Calculator` (main sidebar), enter
   pickup and drop-off addresses, date, horse count. The calculator
   computes the route, adds fuel, fees, VAT, shows net/gross price.
   Click "Save as quote" → you go to `QuoteResource` where you can add
   terms and email the quote to the customer. The customer receives
   a PDF + a signed "Accept quote" URL in their email.

## Lead marketplace

A lead = a request from a customer. It arrives in one of two modes:

- **Broadcast mode.** An anonymous customer from `/transport/zapytanie`
  or a logged-in stable without a chosen favourite — the lead goes to
  every transporter whose service area covers the route (origin
  province + destination + adjacent). Anyone can reply with a quote.
  The customer sees all quotes and picks one. **There is no
  "first-come-first-served"** — you have up to 14 days to respond (the
  lead's `expires_at`).
- **Direct mode.** The customer deliberately picks you (via a star in
  the stable's panel, via the CTA on your public profile, or via a
  partner link) — the lead goes **only to you**. No competition.

Lead inbox: `Leads` in the left sidebar. Click a lead → see details
(route, dates, horse count, notes) → action `Reply with quote`. The
action opens the calculator with addresses pre-filled; add your price
and send.

**What happens when a customer accepts somebody's quote:**

- If they accepted **your** quote: you get `QuoteAcceptedNotification`,
  lead status → `accepted`, you can issue an invoice.
- If they accepted **someone else's**: you get `LeadClosedNotification`
  ("Your quote was not selected — another provider was chosen") or
  `QuoteRejectedNotification` if you had submitted a quote. Your
  quote's status → `rejected`.

## Quotes and invoices

- **Quote** (`Quotes` in sidebar): numbering `OF/YYYY/MM/NNNN`, status
  `draft` → `sent` → `accepted`/`rejected`/`expired`. PDF auto-generated,
  sent through a separate mailer `transport@hovera.app` (separate from
  the main Hovera mailer for better deliverability). Customer accepts
  through a signed URL in the email — no account required.
- **Transport invoice** (`Invoices` in sidebar): issued after delivery.
  The `Issue invoice from quote` action copies items from the quote;
  you only add the delivery date. Your numbering (`FV/YYYY/MM/NNNN`
  by default, configurable in `Settings → Numbering`), your tax ID.
  KSeF — coming soon (Roadmap Phase 9, ETA 2026 Q3).

## Mini-dashboard

`Dashboard` (the default view after login) — 4 widgets:

- **Leads KPI:** weekly lead count + 30-day win rate (percentage of
  quotes accepted vs sent).
- **Upcoming transports:** 7-day forward calendar — accepted quotes
  with `proposed_date` in the window. Clickable, opens the quote.
- **Top invoices 90d:** ranking of best-paid jobs. Helps you identify
  profitable route/customer pairs.
- **Routes heatmap:** which province→province pairs you run most often.
  Aggregated from `transport_lead_responses.distance_km`.

## FAQ

**Does Hovera take liability for damage to the horse / vehicle?**
No. You are the carrier, you carry full liability (CMR Convention +
your carrier insurance). Holding a current carrier liability policy
is a condition of account activation.

**Does Hovera issue transport invoices?**
No. You issue invoices under your tax ID, your numbering. Hovera
provides the tool (`Invoices` in panel) — nothing more.

**What if the customer doesn't pay?**
You collect directly. Hovera does not intermediate transport payments
(unless we later enable Stripe Connect — then you'll be able to enable
card payment at quote acceptance).

**Can I have more than one vehicle?**
Yes — the limit depends on the plan:
- **Solo** — 1 vehicle, 1 driver, marketplace unlimited.
- **Pro** — up to 5 vehicles, 5 drivers, own routing API key,
  map branding.
- **Fleet** — vehicles/drivers unlimited, Google Maps Routes API
  from Hovera's account, priority support.

**Can I change my plan?**
Yes, in `Settings → Subscription → Change plan`. Upgrade is immediate,
downgrade applies at the end of the billing cycle.

**What happens if I don't accept a lead within 14 days?**
The lead expires (`expires_at`). Other quotes (if any) move to
`withdrawn`, the customer is offered "extend / resend". No penalties —
you simply do nothing.

**Can I block a specific customer?**
Not in MVP — if you have a problematic customer, report to
`support@hovera.app`, the master admin can ban or temporarily
suspend them.

**Can I use Hovera as a CRM (customer list, history)?**
Yes — the `Customers` section (per tenant) holds every customer you
have ever quoted. Quote and invoice history, notes. Coming soon: tags
and segmentation.

**Can I do international routes (DE/CZ/SK)?**
In MVP — Poland only. International routes are on the post-MVP roadmap
(Phase 14.4). For now you can issue a quote with a foreign address
manually (the calculator will route it, but there is no legal
validation and no VAT-OSS handling).

**Can I have both a stable and a transport company in Hovera?**
Yes — multi-tenancy. Create a second tenant in `Settings → Account →
Add company`, choose `Transporter` type. The tenant switcher (top of
the screen) flips between them.

## Support

- **Support email:** `support@hovera.app` (response time: 1 business
  day on Solo, 4h on Pro, 1h on Fleet).
- **Documentation:** `docs.hovera.app/transport` (latest version of
  this document + technical references).
- **System status:** `status.hovera.app` (uptime, incidents, scheduled
  maintenance).
- **Bug reporter in panel:** bottom-right corner — modal opens without
  redirect, the report goes straight to us with metadata.
