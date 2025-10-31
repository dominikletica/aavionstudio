# User management dashboard

Status: Draft  
Updated: 2025-10-31

Administrators can review and update user accounts directly from the **Admin → Users** section. The current tooling focuses on core profile data and role management to support early deployments.

## Browsing users

1. Open **Admin → Users** to view all accounts. Use the search bar to filter by email or display name.
2. Apply the status filter to focus on active, pending, disabled, or archived users.
3. Each row lists the assigned roles and the last login timestamp. Click **Edit** to adjust the account.

## Editing an account

1. Update the display name, preferred locale, and timezone to match the user’s profile.
2. Change the account status when onboarding (`pending` → `active`) or suspending access (`disabled`).
3. Toggle roles (viewer, editor, admin, super admin) to grant or revoke privileges. `ROLE_VIEWER` remains assigned automatically to ensure baseline access.
4. Save the form to persist changes. The system records profile and role updates in the audit log for traceability.
5. Issue API keys by providing a label, optional scopes (space/comma separated), and an optional expiry date. Copy the generated secret immediately—it is only shown once. Revoke keys via the list when they are no longer needed.
6. Adjust project access in the **Project access** panel. Choose a project, set a specific role, and (optionally) add extra capabilities that apply only within that project. Leave the role blank to inherit the user’s global permissions.
7. Use **Send password reset email** to trigger a one-time reset link; the system emails the user and records the action in the audit log.

## Next steps

- Future iterations will surface project-specific overrides, password reset actions, and API key management in the same area.
- Until then, administrators can trigger password resets from the login page or by sending invitations again if necessary.
