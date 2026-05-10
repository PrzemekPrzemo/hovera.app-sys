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

- KSeF PDF storage backend (S3/R2 vs local).
- Recurring entries server-side expansion window (currently 60 days).
- Offline > 30 days: token expires; clients fall back to read-only
  cache until reconnect + re-login.
- Bundle / Application IDs (`app.hovera.ios`, `app.hovera.android`)
  pending App Store / Play Store account confirmation.
