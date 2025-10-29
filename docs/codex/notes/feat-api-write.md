# Feat: Write API & Integration Surface (P1 | Scope: M)

**Status:** Draft – final endpoints subject to later review.  
**Intent:** Extend the read-only snapshot API with authenticated write capabilities and integration touchpoints (webhooks, SDKs).

## Use Cases
- External tools creating/updating entities (e.g., headless editors, migration scripts).
- Automation triggers committing changes instantly or staging drafts.
- Integration with partner platforms via webhooks or polling.

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

## Open Questions
- Do we expose granular permissions per schema or entity type?
- Should webhooks support signing secrets per endpoint?
- Is GraphQL support desirable or can be deferred indefinitely?
