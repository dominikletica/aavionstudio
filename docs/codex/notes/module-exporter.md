# Module: Exporter (P1 | Scope: M)

**Status:** Draft proposal – to be refined once core snapshot delivery is stable.  
**Purpose:** Provide configurable exports (JSON, JSONL, optional TOON) derived from published snapshots, with presets and delivery endpoints.

## Capabilities
- Register admin UI under `/admin/exporter` to manage presets and trigger exports.
- Support sync download and async background generation via Messenger.
- Allow export profiles to select entity sets, fields, metadata, and apply content filters.
- Deliver artifacts as downloadable files (ZIP, JSON, JSONL) with retention policy.

## Architecture
- Module manifest registers:
  - Services (`modules/exporter/config/services.php`)
  - Routes (`modules/exporter/config/routes.yaml`)
  - Admin navigation (`Exporter` link, priority 200)
  - Console command `app:exporter:run`
- `ExportPreset` entity stored in `system.brain` (fields: slug, name, format, filters, options, last_run_at).
- `ExportManager` orchestrates generation; uses `SnapshotReader` to fetch data.

## Formats
- **JSON:** Full dataset per preset; optional pretty-print for manual review.
- **JSONL:** Line-delimited records; streaming writer to minimise memory.
- **TOON (optional):** Convert JSON snapshot to TOON format; behind feature flag.

## UI Flow
1. Preset list with status (last run, last outcome).
2. Preset editor (fields selection, filters, metadata such as usage hints).
3. Export run: choose immediate download or queue for background processing.
4. History tab showing completed exports (filename, size, checksum, retention expiry).

## Integration
- Depends on Snapshot feature; optional Messenger for asynchronous runs.
- Exposes webhook/event `ExportCompletedEvent` for future automation.
- Hooks into security to restrict usage to admins with `ROLE_EXPORTER`.

## Implementation Steps
1. Data model + services for presets and export jobs.
2. Admin UI (Stimulus controller + Twig templates) for managing presets.
3. Export execution engine with streaming writers.
4. Optional queue integration and retention cleanup command.

## Risks & Notes
- Large exports: stream to disk, avoid loading entire snapshot.
- Security: ensure exports respect project visibility + user permissions.
- Future: integrate scheduling (cron/queue) and push-to-cloud storage adapters.
