# Feat: Snapshot & API Delivery (P0 | Scope: L)

**Status:** Draft – implementation details may evolve.  
**Objectives:** Persist deterministic JSON snapshots per project and expose them through the Symfony HTTP layer and REST API.

## Snapshot Writer
- Triggered post-commit or manual rebuild.
- Writes to `var/snapshots/<project>/<slug>.json` (configurable segmentation by entity type).
- Uses temp file + atomic rename to avoid partial writes.
- Stores metadata (hash, generated_at, generator_version) in `system.brain`.
- Supports per-project delivery mode (public file vs controller streaming).

## Read Layer
- Frontend controllers load snapshot segments via dedicated `SnapshotReader` service.
- API routes: `/api/v1/projects/{project}/entities/{slug}` etc. respond with snapshot data, not live DB.
- ETag/Last-Modified headers derived from metadata for caching.
- Rate limiting (via Symfony RateLimiter) on API group with per-key quotas.

## Services
- `SnapshotManager`: orchestrates generation, metadata persistence, event dispatch.
- `SnapshotStorage`: abstraction to read/write (local filesystem now, S3 plugin later).
- `SnapshotGuard`: ensures directories exist, handles cleanup of obsolete files.
- `SnapshotChecksumService`: calculates hash + optional signature for integrity.

## API Contracts
- JSON:API-like structure for entity payloads (type, id, attributes, relationships).
- Dedicated route for full project snapshot download (ZIP or JSON).
- Pagination for listing endpoints (`/api/v1/projects/{project}/entities`).
- Authentication via API keys; scopes define read access per project.

## Integration Flow
1. Draft commit triggers resolver → snapshot writer.
2. Snapshot manager queues heavy writes via Messenger if runtime threshold exceeded.
3. Frontend & API controllers request data exclusively through `SnapshotReader`.
4. Exporter module consumes snapshot metadata to build packages.

## Implementation Sequence
1. Define snapshot storage paths + metadata schema.
2. Implement synchronous writer + integration tests (temp rename, hash).
3. Build API controllers + serialization logic (with Symfony Serializer).
4. Introduce optional async via Messenger transport (delayed).
5. Add CLI commands: `snapshot:rebuild`, `snapshot:prune`.

## Considerations
- Shared hosting: ensure `var/snapshots` writable; document fallback when using root loader.
- Large snapshots: consider chunking or streaming; implement incremental diffs later.
- Security: guard controller route when `public delivery` disabled; log snapshot rebuilds.
