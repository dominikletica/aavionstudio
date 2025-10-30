# Feat: Media Storage & Delivery (P0 | Scope: L)

**Status:** Draft – adjustments expected during implementation.  
**Purpose:** Provide upload handling, metadata management, secured delivery, and integration with schema fields and exporter workflows.

## Capabilities
- File ingestion via admin UI (single + bulk) and resolver references.
- Storage layout `data/uploads/<hash>/<filename>` with checksum, MIME, size, owner, ACL metadata persisted in `system.brain`.
- Image derivative support (thumbnails) using on-demand generation (optional phase).
- Protected delivery via controller with signed URL + expiry; public assets served directly.
- Garbage collection for orphaned files and version-aware retention.

## Services
- `MediaStorage` abstraction (local adapter first, future cloud adapters).
- `MediaMetadataRepository` for lookup and lifecycle state.
- `SignedUrlGenerator` using HMAC + TTL; integrates with Download controller.
- Validation pipeline (size/mime whitelist, virus scan hook placeholder).

## Admin Experience
- `/admin/media` browser with filters (project, schema usage, owner).
- Upload widget integrated into Draft editor fields.
- Preview modal (image/video) and metadata editor (alt text, captions).

## Schema Integration
- Schema field type `media` referencing media IDs with constraints (single/multi, type filter).
- Resolver ensures referenced media exists and is published.
- Exporter includes media references + signed download URLs when required.

## Implementation Steps
1. Implement storage abstraction + metadata tables/migrations.
2. Build upload controller (chunked optional) and admin browser UI.
3. Add delivery controller (public + protected) with signature validation.
4. Integrate schema field + editor component.
5. Add cleanup command for orphaned/unreferenced media.
6. Optional: add thumbnail pipeline, remote adapter interface.

## Decisions (2025-10-31)
- Implement optional per-project storage quotas enforced during upload with warning thresholds and hard limits when configured.
- Provide a pluggable virus-scan hook (ClamAV/HTTP) that operators can enable; when unconfigured it is safely skipped.
- Document CDN integration via `ASSET_BASE_URL`/signed URL configuration so public assets can be fronted without custom code.
