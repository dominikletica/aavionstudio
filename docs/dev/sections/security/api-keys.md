# API keys

Status: Draft  
Updated: 2025-10-31

API keys provide programmatic access to aavion Studio. This guide covers issuance, storage, revocation, and testing hooks.

## Storage

- Table: `app_api_key`
  - `id` ULID primary key.
  - `user_id` (FK → `app_user`), `label`, `hashed_key` (SHA-512), `scopes` (JSON array), `last_used_at`, `created_at`, `expires_at`, `revoked_at`.
- Secrets are hashed using SHA-512; the plaintext is shown exactly once when the key is issued.
- Scopes are normalised (trimmed, lower-case preserved) and kept sorted to simplify comparisons.

## Service: `ApiKeyManager`

Located at `src/Security/Api/ApiKeyManager.php`.

- `issue(string $userId, string $label, array $scopes = [], ?DateTimeImmutable $expiresAt = null, ?string $actorId = null)`  
  Inserts a key, hashes the secret, records `api.key.issued` audit event, and returns `['id' => ..., 'secret' => ..., 'label' => ...]`.
- `listForUser(string $userId)` returns `App\Security\Api\ApiKey` value objects for display.
- `get(string $id)` hydrates a single key.
- `revoke(string $id, ?string $actorId = null)` timestamps `revoked_at` and logs `api.key.revoked`.

Audit logging is handled via `SecurityAuditLogger`.

Unit coverage: `tests/Security/Api/ApiKeyManagerTest.php`.

## Admin UI

- Routes handled by `src/Controller/Admin/UserController.php`:
  - `/admin/users/{id}` (GET/POST) – lists keys per user, allows creation via `ApiKeyCreateType`.
  - `/admin/users/{userId}/api-keys/{apiKeyId}/revoke` (POST) – CSRF-protected revoke action.
- Template: `templates/admin/users/edit.html.twig` renders the user form + API key table.
- Functional coverage: `tests/Controller/Admin/UserControllerTest.php::testCreateAndRevokeApiKey`.

## CLI issuance

Command: `app:api-key:issue`

```
php bin/console app:api-key:issue user@example.com --label="CI token" --scope=content.read --scope=content.write --expires=2030-01-01
```

The command resolves the user by ULID or active email, creates the key, and prints the secret once. Useful for bootstrap scripts or CI pipelines.

## Follow-up ideas

- Track `last_used_at` updates via middleware once API authentication is implemented.
- Add pagination and filtering when key volume grows.
- Surface API key history in audit log viewer (forthcoming Admin UI task).
- Consider scoped capability presets for common integrations.
