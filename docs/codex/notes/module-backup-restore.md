# Module: Backup & Restore (P1 | Scope: L)

**Status:** Draft – functionality will evolve with deployment insights.  
**Mission:** Allow administrators to create, download, schedule, and restore backups of databases, snapshots, and uploaded assets.

## Scope
- On-demand backups triggered via `/admin/backup`.
- Scheduled backups (daily/weekly) configurable per project or instance.
- Backup contents: `system.brain`, `user.brain`, `var/uploads`, `var/snapshots`, optional configuration files.
- Export format: compressed archive (`.zip` / `.tar.gz`) with manifest JSON (checksum, versions, timestamp).
- Restore workflow with validation and optional dry-run.

## Architecture
- Module manifest registers services, routes, console commands.
- `BackupManager` orchestrates export pipeline; streams archives to disk before download.
- `RestoreManager` validates manifest, ensures current data is archived before overwrite, executes restore steps sequentially.
- Utilize Symfony Messenger for long-running backup/restore jobs to keep UI responsive.

## Storage & Retention
- Local storage path `var/backups/`; allow configuring remote storage (FTP/S3) later.
- Retention policy (keep last N backups per schedule).
- Integrity check: SHA256 checksum stored in manifest; verify before restore.

## UI/UX
- Dashboard listing backups with date, size, status (success/failed).
- Buttons: Download, Restore, Delete, Schedule toggle.
- Logs displayed with steps and warnings (e.g., missing assets).

### Admin Workflow
1. **Create Backup** button opens modal with scope toggles (database, snapshots, uploads).
2. Background job streams archive; progress bar updates via SSE.
3. Completion screen offers download link + checksum copy button.
4. Restore wizard verifies checksum, lists contents, requests confirmation with typed phrase.

## Implementation Steps
1. Basic backup command (CLI) for manual export; integrate into module services.
2. Admin UI & Messenger job status polling.
3. Restore wizard (upload or select existing backup).
4. Scheduling (Symfony scheduler/cron docs) + retention cleanup.

## Streaming Strategy
- Read-only hosting: function streams archive directly to HTTP response using `ZipStream`.
- Normal mode: temporary file stored under `var/backups/<timestamp>.zip` before exposing download.
- Backup manifest sample:
  ```json
  {
    "version": "1.0.0",
    "generatedAt": "2025-10-30T12:00:00Z",
    "includes": ["system.brain", "user.brain", "snapshots", "uploads"],
    "hash": "sha256:...",
    "encryption": {
      "enabled": true,
      "algorithm": "AES-256-Zip",
      "hint": "Stored in password manager"
    }
  }
  ```

## Scheduling & Retention
- Scheduler configuration stored in `system.brain:backup_schedule`.
- Options: daily/weekly/monthly, time-of-day, retention count.
- Retention job deletes oldest archives beyond limit, respecting locked backups (flag set by admin).
- Read-only mode hides scheduling UI and displays tooltip explaining restriction.

## Decisions (2025-10-30)
- Read-only hosting falls back to streaming archives directly to the requester and disables scheduled jobs/retention that require persistent writes.
- Upload payloads become optional with a default-on toggle to shrink archives when storage is tight.
- Offer passphrase-protected archives (AES-256 Zip); strongly recommended in docs but not enforced by software.
