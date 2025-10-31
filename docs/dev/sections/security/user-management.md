# User management admin UI

Status: Draft  
Updated: 2025-10-31

The `/admin/users` area provides operators with a lightweight interface for reviewing accounts, adjusting profile data, and managing role assignments. This page documents the current implementation so future iterations (project overrides, password resets, API keys) can build on a clear baseline.

## Listing users

- Controller: `src/Controller/Admin/UserController.php::index()` renders `templates/admin/users/index.html.twig`.
- Data source: `UserAdminManager::listUsers()` queries `app_user`, joins `app_user_role`, and returns hydrated rows with basic metadata (status, locale/timezone, last login).
- Filters: query string parameters `q` (matches email or display name) and `status` (active/pending/disabled/archived). Default limit is 100 users ordered by creation date.
- Template: shows a search bar, status filter, roles badges, last-login column, and an action link to the edit view.

## Editing profiles and roles

- Route: `GET|POST /admin/users/{id}` handled by `UserController::edit()`.
- Form: `UserProfileType` (display name, locale, timezone, status, multi-select roles). Role choices come from `app_role` (`UserAdminManager::getRoleChoices()`), ensuring `ROLE_VIEWER` is always available.
- Persistence: `UserAdminManager::updateUser()` runs inside a transaction, updating the `app_user` row, replacing entries in `app_user_role`, and logging changes via `SecurityAuditLogger`.
  - Audit events emitted: `user.profile.updated` (with field diff) and `user.roles.updated` (added/removed arrays).
  - Role updates automatically keep `ROLE_VIEWER` assigned as a baseline.
- Templates render account metadata (email, timestamps) and the Symfony form widgets with simple styling consistent with invitations.

### Project membership overrides

- Additional form: `ProjectMembershipCollectionType` (collection of `ProjectMembershipEntryType`) renders one row per project.
- Data source: `ProjectRepository::listProjects()` and `ProjectMembershipRepository::forUser()` hydrate default values.
- Submissions map to `ProjectMembershipRepository::assign()`/`revoke()` with permissions stored as `['capabilities' => [...]]`. Blank role = inheritance (revoke entry).
- Functional coverage lives in `tests/Controller/Admin/UserControllerTest.php::testUpdateProjectMembership`.
- Capability enforcement is integration-tested via `ProjectCapabilityProbeController` (`/admin/projects/{projectId}/capability/{capability}/probe`) in `tests/Controller/Admin/ProjectCapabilityProbeControllerTest.php`.

### Password reset trigger

- Admins can issue a reset email via the dedicated form on `/admin/users/{id}`.
- Route: `POST /admin/users/{id}/password-reset` with CSRF guard (`admin_user_password_reset_{id}`).
- Implementation reuses `PasswordResetTokenManager`, sends `emails/password_reset.txt.twig`, and logs `auth.password.reset.requested` with the admin as actor.
- Covered by `tests/Controller/Admin/UserControllerTest.php::testSendPasswordResetEmail` (token persisted, email queued, audit entry recorded).

## Tests

- `tests/Controller/Admin/UserControllerTest.php` boots a sandbox schema, seeds roles/users, and asserts:
  - Listing page renders user data.
  - Editing a user updates profile fields, swaps roles, and records audit events.

## Follow-up roadmap

- Extend edit view with project-level overrides and capability inspectors once project voters land in the UI.
- Add quick actions (send password reset, deactivate/reactivate) and surface audit history.
- Introduce paging + richer filtering when the dataset grows.
- Integrate the module-friendly admin shell (sidebar/header) once Step 4 of the roadmap is underway.
