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

## Architecture Overview
- Security bundle configured with user provider backed by `app_user` table (ULID id, email, password hash, profile JSON, locale).
- Permission registry: modules register capabilities (`content.publish`, `export.run`) via manifest; installer seeds roles with defaults.
- Middleware:
  - Voters for entity-level access (draft ownership, project membership).
  - Route attributes for capability checks (`#[IsGranted('export.run')]`).
- Password reset flow: signed URLs + token storage in `system.brain`.

### Authentication Flow
1. Login form posts to `app_login`; rate limited (5 attempts/minute) per IP + email.
2. Successful login rotates session ID and records audit event `auth.login.success`.
3. Optional remember-me cookie uses `APP_SECRET` derived key with 30-day expiry; stored hashed server-side.
4. Logout invalidates all remember-me tokens and session.

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
- `/admin/users` module (core) lists accounts, role assignments, API keys.
- Form-driven editor with validation, optional invitation emails.
- Audit log integration: record login attempts, role changes, API key creation.

## API & Integrations
- Bearer API keys for programmatic access; hashed storage with last-used metadata.
- OAuth2 / SSO left for future module; design interfaces to allow pluggable user providers.
- Webhooks triggerable per module (e.g., notifier for failed login).

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

## Implementation Milestones
1. User entity + Doctrine migration; password hasher config; login/logout controllers.
2. Role hierarchy + capability registry; integrate with module manifest loader.
3. Admin UI for accounts, API keys, role assignment.
4. ACL voters and unit tests covering project/content access scenarios.
5. Optional enhancements: 2FA, account lockout, session revocation.

## Decisions (2025-10-30)
- Add project memberships (`app_project_user`) layered over global roles so editors can be scoped per project without duplicating accounts.
- All role, capability, and API-key changes append to the audit log to guarantee traceability.
- SSO/OAuth ships later as pluggable authenticators; core release relies on password login with optional 2FA.
