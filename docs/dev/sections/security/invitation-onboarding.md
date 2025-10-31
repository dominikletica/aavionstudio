# Invitation onboarding

Status: Draft  
Updated: 2025-10-31

The invitation onboarding flow lets administrators invite collaborators who finish registration themselves. This guide describes the involved services, templates, and tests so contributors can extend or troubleshoot the experience.

## Flow overview

1. An administrator issues an invitation via `/admin/users/invitations`. The backend stores a ULID-based record in `app_user_invitation` with a hashed token and appends an audit entry (`user.invitation.created`).
2. The invitee follows `/invite/{token}`. `InvitationAcceptController` verifies the token, renders `invitation/accept.html.twig`, and displays the onboarding form (`InvitationAcceptType`).
3. On submission, `UserCreator` generates a hashed password, inserts the new account plus `ROLE_VIEWER` membership, and logs `user.created.invitation`. `UserInvitationManager::accept()` switches the invitation to `accepted` and records the audit trail.
4. Successful onboarding redirects back to `/login` so the invitee can sign in immediately.

## Components

- Controller: `src/Controller/Security/InvitationAcceptController.php` – token lookup, CSRF-protected form handling, duplicate email guard, flash messaging.
- Form type: `src/Form/Security/InvitationAcceptType.php` – display name + repeated password fields with validation rules.
- User creation service: `src/Security/User/UserCreator.php` – centralises ULID generation, password hashing, default role assignment, and audit logging. Defaults (locale/timezone/roles) live here and should eventually be overridable via configuration.
- Invitation manager: `src/Security/User/UserInvitationManager.php` – create/accept/cancel invitations, token hashing, TTL management, audit integration, and listing helpers.
- Template: `templates/invitation/accept.html.twig` – renders the onboarding card and links back to `/login`.

Keep these building blocks aligned: when adding fields to the form, update `UserCreator`, the template, and the functional test together.

## Validation and edge cases

- Expired or cancelled tokens redirect back to `/login` with an error flash. Tokens are treated case insensitive and stored using SHA-256 hashes to avoid leaking raw values.
- Submitting an email that already exists raises a `UniqueConstraintViolationException`; the controller catches it and attaches a user-friendly form error instead of exposing database messaging.
- `UserCreator` currently hard codes default roles (`ROLE_VIEWER`), locale (`en`), and timezone (`UTC`). When localisation lands, extract these defaults into container parameters or a configuration object so invitations honour tenant settings.

## Tests and tooling

- Functional coverage: `tests/Controller/Security/InvitationAcceptControllerTest.php` exercises the happy path, ensures the invitation state flips to `accepted`, verifies the password hash, and performs a full login with the new credentials to confirm the account is usable.
- Supporting unit tests: `tests/Security/User/UserInvitationManagerTest.php` (manager CRUD/auditing) and `tests/Security/User/UserCreatorTest.php` (to be written once additional creation flows are implemented).
- Run the full suite with `php bin/phpunit`. The kernel bootstraps SQLite in memory; table setup inside the functional test mirrors the migration schema to keep assertions deterministic.

## Follow-up ideas

- Parameterise defaults in `UserCreator` via configuration (`security.invitation.default_roles`, locale/timezone hints) to support regional onboarding.
- Extend the onboarding form with optional profile fields (timezone/locale selection) once the account settings UI is ready; update validation and tests accordingly.
- Add Panther coverage once the Admin Studio shell is available to validate invitation acceptance from the browser perspective, including flash/redirect behaviour.
