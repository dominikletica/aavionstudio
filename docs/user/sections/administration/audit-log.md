# Security audit log

Status: Draft  
Updated: 2025-10-31

Use the audit log to review security-sensitive actions such as logins, role changes, invitation updates, and API key operations.

## Accessing the log

- Navigate to **Admin → Security → Audit log** (temporary layout: `/admin/security/audit`).
- The table lists recent events in reverse chronological order. Each row shows the timestamp, action key, actor, subject, and JSON context payload.

## Filtering

1. **Action** – choose from known action keys (e.g. `user.role.assigned`, `api.key.issued`).
2. **Actor** – filter by email or ULID; partial email matches are accepted.
3. **Subject ID** – restrict entries to a specific user/invitation/entity ULID.
4. **Date range** – provide `YYYY-MM-DD` (or ISO 8601 date/time) in the “From” and “To” fields to narrow the window.
5. Click **Apply filters**; use **Reset** to clear.

## Tips

- Context data is stored as JSON; copy-paste into your tooling for deeper analysis if needed.
- `System` entries indicate automated actions (e.g. maintenance scripts) with no associated user.
- Combine actor + action filters to trace specific flows (e.g. API key changes by an administrator).
