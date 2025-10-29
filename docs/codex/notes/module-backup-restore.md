# Module: Backup & Restore (P1 | Scope: L)

**Status:** Draft – functionality will evolve with deployment insights.  
**Mission:** Allow administrators to create, download, schedule, and restore backups of databases, snapshots, and uploaded assets.

## Scope
- On-demand backups triggered via `/admin/backup`.
- Scheduled backups (daily/weekly) configurable per project or instance.
- Backup contents: `system.brain`, `user.brain`, `data/uploads`, `data/snapshots`, optional configuration files.
- Export format: compressed archive (`.zip` / `.tar.gz`) with manifest JSON (checksum, versions, timestamp).
- Restore workflow with validation and optional dry-run.

## Architecture
- Module manifest registers services, routes, console commands.
- `BackupManager` orchestrates export pipeline; streams archives to disk before download.
- `RestoreManager` validates manifest, ensures current data is archived before overwrite, executes restore steps sequentially.
- Utilize Symfony Messenger for long-running backup/restore jobs to keep UI responsive.

## Storage & Retention
- Local storage path `data/backups/`; allow configuring remote storage (FTP/S3) later.
- Retention policy (keep last N backups per schedule).
- Integrity check: SHA256 checksum stored in manifest; verify before restore.

## UI/UX
- Dashboard listing backups with date, size, status (success/failed).
- Buttons: Download, Restore, Delete, Schedule toggle.
- Logs displayed with steps and warnings (e.g., missing assets).

## Implementation Steps
1. Basic backup command (CLI) for manual export; integrate into module services.
2. Admin UI & Messenger job status polling.
3. Restore wizard (upload or select existing backup).
4. Scheduling (Symfony scheduler/cron docs) + retention cleanup.

## Open Questions
- How do we handle backups on read-only hosting environments (ZIP streaming only)?
- Should uploads be optional to reduce archive size (checkbox per backup)?
- Do we need encryption for backups by default (password-protected archives)?
