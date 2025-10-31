# Invitation management

Status: Draft  
Updated: 2025-10-31

Use invitations to onboard new teammates without creating passwords manually. Administrators generate a secure link, invitees finish registration, and the platform logs every step for auditing.

## Creating invitations

1. Sign in as an administrator and browse to **Admin → Users → Invitations**.
2. Click **Invite user**, enter the recipient's email address, and (optionally) add a short note or project context.
3. Submit the form. The system stores a pending invitation, sends the email, and shows the invite in the list with its expiration date (7 days by default).
4. Share the link manually if email delivery is disabled—the token is displayed once immediately after creation.

## Tracking status

- **Pending** – Invitation is waiting for the recipient. You can cancel it at any time; cancelled entries cannot be reactivated.
- **Accepted** – The invitee finished onboarding. Accepted rows remain for audit purposes and no longer allow login via the token.
- **Expired** – Tokens that time out are marked as expired when a user attempts to open them. Send a fresh invitation to continue.
- Each action (create, cancel, accept) is recorded in the audit log so you can trace who made the change and when.

## Invitee onboarding

- Invitees follow the `/invite/{token}` link in their email. The form prompts for a display name and a new password; both fields must pass the built-in validation rules (minimum 8 characters for passwords).
- After submission, the platform creates the account with the default viewer role and redirects the invitee to the `/login` page. They can sign in immediately using the password they just set.
- If the token is invalid, expired, or already used, the invitee sees an error and is redirected to `/login`. Ask an administrator to issue a new invitation in that case.

## Troubleshooting

- **“Invitation is invalid or has expired.”** – The token was cancelled or timed out. Create a new invitation.
- **“An account with this email already exists.”** – The user was already added manually or completed a previous invitation. Update their role assignments instead of sending a new invite.
- **No email received.** – Verify the mail transport configuration under **Admin → Settings** or copy the one-time link shown immediately after creating the invitation.
