# Feat: Media Storage & Delivery (P0 | Scope: L)

**Status:** Draft – adjustments expected during implementation.  
**Purpose:** Provide upload handling, metadata management, secured delivery, and integration with schema fields and exporter workflows.

## Capabilities
- File ingestion via admin UI (single + bulk) and resolver references.
- Storage layout `var/uploads/<hash>/<filename>` with checksum, MIME, size, owner, ACL metadata persisted in `system.brain`.
- Image derivative support (thumbnails) using on-demand generation (optional phase).
- Protected delivery via controller with signed URL + expiry; public assets served directly.
- Garbage collection for orphaned files and version-aware retention.
- Canonical media URLs generated via `MediaUrlGenerator` (e.g., `/media/01H.../hero.jpg`), ensuring exporters/API embed a stable, public URL.

## Services
- `MediaStorage` abstraction (local adapter first, future cloud adapters).
- `MediaMetadataRepository` for lookup and lifecycle state.
- `SignedUrlGenerator` using HMAC + TTL; integrates with Download controller.
- Validation pipeline (size/mime whitelist, virus scan hook placeholder).

### Storage Interfaces
- `MediaStorageInterface::store(Stream $stream, MediaDescriptor $descriptor): MediaId`
- `MediaStorageInterface::delete(MediaId $id): void`
- `MediaStorageInterface::open(MediaId $id): Stream`
- Default implementation writes to `var/uploads/<bucket>/<file>`; buckets hashed by ULID prefix and overridable via admin settings.

## Admin Experience
- `/admin/media` browser with filters (project, schema usage, owner).
- Upload widget integrated into Draft editor fields.
- Preview modal (image/video) and metadata editor (alt text, captions).

### Admin UI Features
- Bulk actions: move to collection, set ACL, regenerate derivatives.
- Quota indicator per project (progress bar with warning at 80% usage, hard stop at 100%).
- Virus scan results displayed per asset with re-scan button when hook enabled.

## Schema Integration
- Schema field type `media` referencing media IDs with constraints (single/multi, type filter).
- Resolver ensures referenced media exists and is published.
- Exporter includes media references + canonical URLs (public or signed) so consumers and the frontend resolve identical assets.

## Implementation Steps
1. Implement storage abstraction + metadata tables/migrations.
2. Build upload controller (chunked optional) and admin browser UI.
3. Add delivery controller (public + protected) with signature validation.
4. Integrate schema field + editor component.
5. Add cleanup command for orphaned/unreferenced media.
6. Optional: add thumbnail pipeline, remote adapter interface.

## Quota Enforcement
- Installer default: quotas disabled; admin can set per project (MB/GB) via settings UI.
- Upload pipeline checks `current_usage + file_size` before persisting.
- When exceeding soft threshold (90%), display warning toast and send notification event.

## Virus Scan Hook
- Configure via `.env`:
  ```
  MEDIA_SCAN_DRIVER=clamd
  MEDIA_SCAN_ENDPOINT=tcp://127.0.0.1:3310
  ```
- Hook interface:
  ```php
  interface MediaScannerInterface
  {
      public function scan(FileDescriptor $file): ScanResult;
  }
  ```
- Failure handling: mark asset as `quarantined`, block download until approved.

## URL Generation & Delivery
```php
$signature = hash_hmac('sha256', sprintf('%s|%s|%d', $mediaId, $projectId, $expiresAt), $secret);
$canonicalUrl = $urlGenerator->absoluteUrl('media_show', ['id' => (string) $mediaId]);
$signedUrl = sprintf('%s?expires=%d&sig=%s', $canonicalUrl, $expiresAt, $signature);
```
- Download controller validates expiration and project membership.
- Canonical URL always resolves (either direct file response for public assets or redirect to signed URL when protection required).
- Option to force download vs. inline based on metadata.

## Decisions (2025-10-31)
- Implement optional per-project storage quotas enforced during upload with warning thresholds and hard limits when configured.
- Provide a pluggable virus-scan hook (ClamAV/HTTP) that operators can enable; when unconfigured it is safely skipped.
- Document CDN integration via `ASSET_BASE_URL`/signed URL configuration so public assets can be fronted without custom code.
