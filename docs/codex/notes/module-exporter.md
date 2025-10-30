# Module: Exporter (P1 | Scope: M)

**Status:** Draft proposal – to be refined once core snapshot delivery is stable.  
**Purpose:** Provide configurable exports (JSON, JSONL, optional TOON) derived from published snapshots, with presets and delivery endpoints.

## Capabilities
- Register admin UI under `/admin/exporter` to manage presets and trigger exports.
- Support sync download and async background generation via Messenger.
- Allow export profiles to select entity sets, fields, metadata, and apply content filters.
- Deliver artifacts as downloadable files (ZIP, JSON, JSONL) with retention policy.
- Provide optional in-editor context streaming for LLM-assisted authoring via lazy-loaded export slices.

## Architecture
- Module manifest registers:
  - Services (`modules/exporter/config/services.php`)
  - Routes (`modules/exporter/config/routes.yaml`)
  - Admin navigation (`Exporter` link, priority 200)
  - Console command `app:exporter:run`
- `ExportPreset` entity stored in `system.brain` (fields: slug, name, format, filters, options, last_run_at).
- `ExportManager` orchestrates generation; uses `SnapshotReader` to fetch data.
- `ContextStreamer` service reuses preset configuration to hydrate editor-side AI panels without duplicating pipelines.

## Formats
- **JSON:** Full dataset per preset; optional pretty-print for manual review.
- **JSONL:** Line-delimited records; streaming writer to minimise memory.
- **TOON (optional):** Convert JSON snapshot to TOON format; behind feature flag.

## UI Flow
1. Preset list with status (last run, last outcome).
2. Preset editor (fields selection, filters, metadata such as usage hints).
3. Export run: choose immediate download or queue for background processing.
4. History tab showing completed exports (filename, size, checksum, retention expiry).
5. Context streaming tab toggles which presets feed LLM helper drawer (lazy fetch per editor session).

### Preset Schema
```json
{
  "slug": "llm-blog-context",
  "format": "jsonl",
  "scope": {
    "projects": ["blog"],
    "schemas": ["blog_post", "blog_category"]
  },
  "filters": [
    { "field": "status", "operator": "equals", "value": "published" }
  ],
  "fields": ["title", "summary", "body", "media.header_image_url"],
  "options": {
    "includeCanonicalMediaUrl": true,
    "maxRecords": 1000,
    "anonymiseAuthors": false
  }
}
```

## Integration
- Depends on Snapshot feature; optional Messenger for asynchronous runs.
- Exposes webhook/event `ExportCompletedEvent` for future automation.
- Hooks into security to restrict usage to admins with `ROLE_EXPORTER`.
- LLM helper drawer in Admin Studio subscribes to presets flagged `llmContext: true`; content loads lazily with pagination to avoid performance regressions.
- Canonical media URLs provided by Media module ensure export consumers and frontend share identical asset references.

## Implementation Steps
1. Data model + services for presets and export jobs.
2. Admin UI (Stimulus controller + Twig templates) for managing presets.
3. Export execution engine with streaming writers.
4. Optional queue integration and retention cleanup command.
5. Context streamer integration with Admin Studio editor panel (lazy fetch + caching).

## Risks & Notes
- Large exports: stream to disk, avoid loading entire snapshot.
- Security: ensure exports respect project visibility + user permissions.
- Future: integrate scheduling (cron/queue) and push-to-cloud storage adapters.
