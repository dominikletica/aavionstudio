# Feat: Write API & Integration Surface (P1 | Scope: M)

**Status:** Draft – final endpoints subject to later review.  
**Intent:** Extend the read-only snapshot API with authenticated write capabilities and integration touchpoints (webhooks, SDKs).

## Use Cases
- External tools creating/updating entities (e.g., headless editors, migration scripts).
- Automation triggers committing changes instantly or staging drafts.
- Integration with partner platforms via webhooks or polling.

## Request/Response Schemas
- `POST /projects/{project}/entities`
  ```json
  {
    "slug": "about",
    "schema": "page",
    "payload": { "...": "..." },
    "meta": {
      "commitMessage": "Create about page",
      "publish": false
    }
  }
  ```
  - Returns `201 Created` with body `{ "entityId": "...", "draftId": "...", "status": "draft" }`.
- Validation errors return `422` with machine-readable codes (`PAYLOAD_INVALID`, `SCHEMA_NOT_FOUND`).

## API Design
- REST endpoints under `/api/v1/admin/...` requiring API key with `write` scope.
- Core routes:
  - `POST /projects/{project}/entities` → create draft (payload + schema id)
  - `PUT /projects/{project}/entities/{slug}` → update existing (draft or instant commit)
  - `POST /projects/{project}/entities/{slug}/commit` → promote draft with message
  - `POST /projects/{project}/snapshots/regenerate` → queue snapshot rebuild
- Support `dry_run=true` to validate payload without persisting.

## Security
- HMAC request signing (optional) to prevent tampering.
- Rate limits separate from read API; configurable burst/steady tokens.
- Fine-grained scopes (`content.write`, `snapshot.manage`, `export.run`).

### Rate Limiting
- Default: 60 write requests/minute per API key, configurable via admin UI.
- Burst limiter resets after 10 seconds; informative `429` responses include `Retry-After`.
- Admin UI displays usage graphs and top endpoints.

## Webhooks
- Deliver events (`entity.committed`, `snapshot.published`, `export.completed`) to configured URLs.
- Retry with exponential backoff; log failures with admin notifications.

## SDK / CLI
- Provide PHP/JS SDK interfaces for common operations (optional step).
- CLI commands for import/export use the same API service for consistency.

## Implementation Steps
1. Define API request/response schemas and integrate with Symfony Serializer.
2. Implement authentication guard for API keys + scope checks.
3. Build controllers delegating to existing services (DraftManager, SnapshotManager).
4. Add webhook dispatcher + delivery queue (Messenger).
5. Publish API reference (OpenAPI spec) and developer quickstart docs.

## Error Catalogue
- `AUTH_SCOPE_MISSING`: API key lacks required scope.
- `PROJECT_NOT_FOUND`: Provided project slug invalid.
- `ENTITY_LOCKED`: Draft locked by another user/process.
- `SNAPSHOT_BUSY`: Snapshot regeneration already queued; returns existing job ID.

## SDK Considerations
- PHP SDK wraps endpoints with strongly typed DTOs, includes middleware for HMAC signing.
- JS SDK targets Node + browser (fetch-based) with TypeScript definitions.
- CLI tool (optional) consumes SDK but remains developer-only; production admins rely on UI.

## Decisions (2025-10-30)
- Initial scope relies on project-level capabilities; schema-specific permissions are deferred until required.
- Webhooks carry per-endpoint signing secrets with rotation support to satisfy security audits.
- GraphQL remains out of scope for launch; reassess once REST usage matures.
