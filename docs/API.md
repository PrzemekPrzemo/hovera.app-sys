# Hovera Mobile API (v1)

The v1 API drives the iOS and Android apps. It is **stateless** (no
session cookies), **token-based** (Sanctum personal access tokens), and
**multi-tenant** (every request beyond `/auth/login` carries an
`X-Tenant-Id` header). All timestamps are ISO 8601 UTC.

## Auth flow

1. `POST /api/v1/auth/login` with `{ email, password, device_name? }` returns:
   ```json
   {
     "token": "hov_xxxxxxxxxxxx",
     "expires_at": "2026-06-09T12:00:00Z",
     "user": { "id": "...", "email": "...", "locale": "pl" },
     "memberships": [
       { "tenant": { "id": "...", "name": "Stajnia A" }, "role": "manager", "permissions": {} }
     ]
   }
   ```
2. The mobile app stores the token in Keychain (iOS) or
   EncryptedSharedPreferences (Android), lets the user pick a tenant
   from `memberships`, and caches the choice.
3. Every subsequent request sends:
   ```
   Authorization: Bearer hov_xxxxxxxxxxxx
   X-Tenant-Id: 01HW3K000000000000000ABCDE
   Accept-Language: pl-PL
   ```
4. `POST /api/v1/auth/refresh` rotates the token (issues a new one and
   revokes the current). Mobile clients refresh proactively when the
   token age exceeds 21 days.
5. `POST /api/v1/auth/logout` revokes the current token.

## Sync protocol

### Pull — `GET /api/v1/sync/changes?since={cursor}&entities=...&limit=200`

```json
{
  "cursor": "MTI6MTczNDU2",
  "has_more": false,
  "changes": [
    {
      "entity": "calendar_entries",
      "op": "upsert",
      "id": "01HW...",
      "sync_version": 173456,
      "updated_at": "2026-05-10T08:14:22Z",
      "payload": { "id": "01HW...", "title": "...", "...": "..." }
    },
    {
      "entity": "horses",
      "op": "delete",
      "id": "01HV...",
      "sync_version": 173401
    }
  ]
}
```

- `cursor` is opaque base64 (`{tenant_id}:{sync_version}`); pass it back
  as `since=...` on the next call.
- `has_more=true` means another page is available immediately.
- Soft-deleted rows surface as `op: "delete"`. Hard deletes are not
  expected (we soft-delete everything).

### Push — `POST /api/v1/sync/mutations`

```json
{
  "mutations": [
    {
      "client_uuid": "9f1c...",
      "idempotency_key": "deviceA:9f1c...:create",
      "entity": "calendar_entries",
      "op": "create",
      "payload": { "id": "9f1c...", "starts_at": "...", "...": "..." },
      "base_version": 173400
    }
  ]
}
```

Response (per mutation, ordered):

```json
{
  "results": [
    { "client_uuid": "9f1c...", "status": "applied", "server_id": "9f1c...", "sync_version": 173457 },
    { "client_uuid": "77ab...", "status": "conflict",
      "conflict_type": "booking_overlap",
      "current_server_state": { "...": "..." },
      "errors": { "starts_at": ["Overlaps existing entry #abc"] } },
    { "client_uuid": "22cd...", "status": "duplicate", "sync_version": 173410 }
  ]
}
```

Conflict types you may receive:

| `conflict_type`      | Meaning                                                   |
|----------------------|-----------------------------------------------------------|
| `forbidden`          | Membership role cannot mutate this entity.                |
| `unsupported_entity` | Entity name not in `config/sync.php`.                     |
| `invalid_op`         | Op other than `create` / `update` / `delete`.             |
| `not_found`          | Update/delete targeted a row the server doesn't have.     |
| `append_only`        | Entity is append-only; only `create` is permitted.        |
| `booking_overlap`    | Calendar entry would overlap a confirmed booking.         |
| `server_error`       | Unexpected exception; safe to retry with backoff.         |

Idempotency: a `(idempotency_key)` already processed within 14 days is
returned with `status: "duplicate"` and the original `sync_version`,
allowing the client to drain its queue without double-applying.

### Photos

1. Compute `sha256` of the file.
2. `POST /api/v1/uploads/horse-photos` with `{ sha256, mime, byte_size }`
   → `{ storage_key, upload_url, method, headers, expires_at }`.
3. PUT the bytes to `upload_url` with the returned `headers`.
4. Reference `storage_key` from a normal `horse_photos` create mutation.

## Invoices

- `GET /api/v1/invoices` — list, newest `issued_at` first.
- `GET /api/v1/invoices/{id}` — single invoice.
- `GET /api/v1/invoices/{id}/pdf` — the invoice PDF.

Each invoice resource includes `amount_cents`, `ksef_status`,
`ksef_reference_number`, `ksef_environment` and `pdf_url` (an API URL, not
a raw storage link).

**PDF hosting policy** (resolved 2026-07): hovera.app hosts the invoice
PDF on its own storage (local disk by default; configurable to S3/R2 via
`INVOICE_PDF_DISK`, see `config/invoicing.php`) for the calendar year it
was issued in, plus a 1-month grace period — an invoice issued in year Y
stays hosted through the end of January of year Y+1. A daily job
(`invoices:prune-expired-pdfs`) deletes the file and clears the
`pdf_*` columns once that cutoff passes.

Once the cutoff has passed, `GET /api/v1/invoices/{id}/pdf` no longer
returns a PDF. It responds `410 Gone` instead:

```json
{
  "error": { "code": "pdf_no_longer_hosted", "message": "..." },
  "ksef_reference_number": "...",
  "ksef_environment": "production",
  "ksef_portal_url": "https://ksef.mf.gov.pl"
}
```

Every invoice submitted to KSeF has a permanent record there, so the
client should point the user at `ksef_portal_url` (the KSeF taxpayer web
portal for the invoice's environment) together with
`ksef_reference_number` to look it up. **Known limitation:** we do not
currently construct a verified deep-link to the specific invoice inside
KSeF — the portal's public verification/redownload query format (it may
require NIP + amount + date, and may have changed over time) hasn't been
confirmed. `ksef_portal_url` is the portal's base URL only; wiring up an
exact deep-link is follow-up work once the format is confirmed.

## Devices & push

- `POST /api/v1/devices` registers an APNs (iOS) or FCM (Android) token.
  Idempotent on `(token)` — duplicate calls update `last_seen_at`.
- `DELETE /api/v1/devices/{token}` unregisters (logout / app reinstall).

The server sends:

- **silent** push (`content-available: 1`) on calendar / message
  changes — mobile triggers an immediate sync.
- **visible** push for new client / horse messages, invoices and
  stable activity assignments.

## Rate limits

- 120 requests / minute / token for general endpoints.
- 30 requests / minute / token for `sync/*` and `uploads/*`.

429 response carries `Retry-After: <seconds>` and an
`X-RateLimit-Remaining` header.

## Open issues

Tracked in the implementation plan (`docs/plans/...`):

- ~~KSeF PDF storage backend (S3/R2 vs local).~~ Resolved 2026-07: local
  disk hosting (configurable to S3/R2 via `INVOICE_PDF_DISK`) for the
  issued year + 1 month grace, then the API returns a KSeF-redirect
  payload instead of the PDF — see the "Invoices" section above. Still
  open: a verified KSeF deep-link query format for a specific invoice
  (currently we only expose the portal base URL + reference number).
- Recurring entries server-side expansion window (currently 60 days).
- Offline > 30 days: token expires; clients fall back to read-only
  cache until reconnect + re-login.
- Bundle / Application IDs (`app.hovera.ios`, `app.hovera.android`)
  pending App Store / Play Store account confirmation.
