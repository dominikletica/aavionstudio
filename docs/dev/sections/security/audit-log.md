# Audit log viewer

Status: Draft  
Updated: 2025-10-31

The audit log viewer surfaces security-relevant events recorded via `SecurityAuditLogger`. It enables administrators to inspect changes to accounts, roles, invitations, and API keys.

## Repository

`src/Security/Audit/SecurityAuditRepository.php`

- `search()` filters by action prefix, actor (email or ID), subject ULID, and optional date range. Results include decoded JSON context plus actor metadata.
- `getAvailableActions()` returns distinct action keys for dropdown filters.
- Context is decoded safely; malformed JSON is returned under a `raw` key.

## Controller & Route

- `src/Controller/Admin/SecurityAuditController.php`
- Route: `GET /admin/security/audit` (`admin_security_audit`)
- Query parameters: `action`, `actor`, `subject`, `from`, `to` (ISO/`Y-m-d` accepted). Invalid dates are ignored gracefully.

## Template

- `templates/admin/security/audit.html.twig`
- Provides filter form + table view. Context is rendered as pretty-printed JSON within a dark `<pre>` block. Actors display email + display name when available; system entries show “System”.

## Tests

- Functional coverage in `tests/Controller/Admin/SecurityAuditControllerTest.php` ensures listing renders entries and filtering by action works.

## Roadmap notes

- Planned enhancements: pagination, CSV export, direct links to user detail pages, contextual icons per event type.
- Once the admin shell (Roadmap #4) lands, port the viewer into the shared layout and add navigation entry under “Security”.
