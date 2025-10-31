# Feat: User Management & Access Control (P0 | Scope: L)

**Status:** Draft – details may be refined during implementation.  
**Objective:** Provide authentication, role-based authorisation, and fine-grained ACLs across core and modular features.

## Functional Requirements
- User accounts with password-based login (Symfony Security) and optional 2FA (future phase).
- Role hierarchy (`ROLE_VIEWER`, `ROLE_EDITOR`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`) plus feature-scoped permissions.
- ACL policies that map module capabilities (e.g., Exporter, Relation Manager) to roles.
- Session management with remember-me support; enforce secure cookies and CSRF protection for all forms/actions.
- API keys per user with scopes for read/write operations.

## Data Model
- `app_user`: `id` (ULID), `email`, `password_hash`, `display_name`, `locale`, `timezone`, `flags` (json), `last_login_at`, `created_at`.
- `app_role`: canonical roles (`viewer`, `editor`, `admin`, `super_admin`); exposed via enum for translations.
- `app_user_role`: pivot table linking users ↔ roles.
- `app_project_user`: `project_id`, `user_id`, `role` (inherit from base or override), `permissions_json`.
- `app_api_key`: `id`, `user_id`, `name`, `hashed_key`, `scopes`, `last_used_at`, `expires_at`.
- `app_audit_log`: `id`, `actor_id`, `action`, `payload_json`, `created_at`, `ip_hash`.
- `app_password_reset_token`: `id`, `user_id`, `selector`, `verifier_hash`, `expires_at`, `consumed_at`.
- `app_remember_me_token`: `series`, `token_hash`, `class`, `user_id`, `last_used_at`.
- `app_user_login_throttle`: `user_id`, `ip_hash`, `attempts`, `last_attempt_at` (optional table if cache store insufficient).

## Architecture Overview
- Security bundle configured with user provider backed by `app_user` table (ULID id, email, password hash, profile JSON, locale).
- Permission registry: modules register capabilities (`content.publish`, `export.run`) via manifest; installer seeds roles with defaults while the runtime `CapabilityRegistry` + `CapabilitySynchronizer` hydrate and persist mappings into `app_role_capability` (with audit log entries).
- Middleware:
  - Voters for entity-level access (draft ownership, project membership).
  - Route attributes/attributes for capability checks (`#[IsGranted('export.run')]`, custom `#[RequiresCapability('content.publish')]`).
- Password reset flow: signed URLs + selector/verifier tokens stored in `system.brain` via `PasswordResetTokenManager`; tokens hashed, expiring (default 1h) with purge task.
- `security.yaml` roles: `ROLE_VIEWER`, `ROLE_EDITOR`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`; global role hierarchy for coarse permissions + capability-based voters.
- Password hashing via Symfony password hasher config (`auto`, Argon2id preferred). Login listener rehashes when algorithm cost changes.
- Rate limiting uses Symfony RateLimiter (IP- + email-based) backed by cache; optional DB table for audit.

### Authentication Flow
1. Login form posts to `app_login`; rate limited (5 attempts/minute) per IP + email.
2. Successful login rotates session ID, records last login timestamp, and appends audit event `auth.login.success`.
3. Optional remember-me cookie uses `APP_SECRET`-derived key with 30-day expiry; server stores hashed tokens in `app_remember_me_token`.
4. Logout invalidates all remember-me tokens and session (`security.logout.handler`).
5. Password reset request stores selector/verifier hash; email includes signed URL; consuming token resets password, revokes remember-me tokens, and logs `auth.password.reset.completed`.
6. Invitation flow optionally creates inactive users with temporary password token.

### Capability Registry
- Modules declare capabilities in manifest:
  ```yaml
  capabilities:
    - key: content.publish
      label: "Publish Content"
      default_roles: ["ROLE_EDITOR", "ROLE_ADMIN"]
  ```
- Registry merges all manifests, storing in `system.brain:capabilities`.
- Installer seeds base roles with capability lists; admin UI allows toggling per role with safety checks for circular dependencies.

## Admin UI
- `/admin/users` module (core) lists accounts, role assignments, per-project overrides, API keys; invitation management UI (`/admin/users/invitations`) already in place.
- Form-driven editor with validation, optional invitation emails, password reset trigger.
- Audit log integration: record login attempts, role changes, API key creation.
- Bulk actions for enabling/disabling users, resetting 2FA (future), revoking sessions.
- Table filters by role, project, status; search by email/display name.

## API & Integrations
- Bearer API keys for programmatic access; hashed storage with last-used metadata + optional expiry.
- OAuth2 / SSO left for future module; design interfaces to allow pluggable user providers.
- Webhooks triggerable per module (e.g., notifier for failed login).
- CLI commands for API key creation/revocation to support headless automation.
- API scope model maps to capabilities (`content.read`, `content.write`, `admin.*`) to reuse registry.

## Audit Logging
- Event schema:
  ```json
  {
    "action": "role.assigned",
    "actor": "01HZYCPK8VG3Z405M3H9H7A1JV",
    "subject": "01HZYCR08VV6K60ZC3T0131H6Z",
    "metadata": {
      "role": "ROLE_EDITOR",
      "project": "default"
    }
}
```
- Log viewer filters by action, user, project, date range.
- Retention policy configurable (default 180 days) with purge job via maintenance module.
- Core events: `auth.login.success`, `auth.login.failed`, `auth.password.reset.requested`, `auth.password.reset.completed`, `user.role.assigned`, `user.role.revoked`, `user.project_role.assigned`, `api.key.created`, `api.key.revoked`.

## Implementation Milestones
1. User entity + Doctrine migration; security firewall, password hasher, login/logout/remember-me + reset tokens; rate limiting + audit logging foundations.
2. Role hierarchy + capability registry integration; seed defaults; expose capability parameter/service.
3. Project membership model + voters; ensure module capability checks use voters; augment audit log events.
4. Admin UI for accounts, project assignments, API keys; invitation + password reset flows; CLI helpers (backend invitation manager already in place).
5. API key issuance + scope enforcement (HTTP + CLI); document usage; cover with tests.
6. Optional enhancements: 2FA, account lockout, session revocation dashboards, webhook notifications.

## Decisions (2025-10-30)
- Add project memberships (`app_project_user`) layered over global roles so editors can be scoped per project without duplicating accounts.
- All role, capability, and API-key changes append to the audit log to guarantee traceability.
- SSO/OAuth ships later as pluggable authenticators; core release relies on password login with optional 2FA.
