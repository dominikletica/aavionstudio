# Module: Admin Maintenance Console (P1 | Scope: M)

**Status:** Draft – details may adjust as other systems solidify.  
**Mission:** Offer a unified interface for operational tools (cache, snapshot rebuild, export triggers, migration runner).

## Capabilities
- Dashboard under `/admin/maintenance` with cards for:
  - Cache operations (`cache:clear`, `cache:warmup`)
  - Snapshot rebuild per project
  - Messenger queue monitoring + retry/failed job actions
  - Doctrine migrations (check pending, run within safe mode)
  - System health (filesystem permissions, disk usage, PHP extensions)
- Provide REST endpoints for AJAX-triggered actions with progress feedback.

## Architecture
- Module manifest registers services, routes, navigation item (`Maintenance`, priority 50).
- Uses Symfony Messenger for long-running tasks triggered from UI (e.g., snapshot rebuild).
- Health checks implemented via `Symfony\Component\DependencyInjection\Compiler\ExtensionCompilerPassInterface` or dedicated services.

## UI/UX
- Cards with status badges (OK / Warning / Action required).
- Modal confirmations for destructive actions (cache clear, migration run).
- Activity log table showing recent maintenance actions (user, timestamp, outcome).

## Integration
- Hooks into `SnapshotManager`, `ExportManager`, `Messenger` to display status.
- Security: restricted to users with `ROLE_SUPER_ADMIN` (or dedicated `ROLE_MAINTENANCE`).
- Emits events (`MaintenanceActionLoggedEvent`) for audit logging.

## Implementation Plan
1. Build controller + Twig templates for dashboard layout.
2. Wire endpoints to existing Symfony commands/services (wrap CLI operations in services).
3. Integrate Messenger monitoring (use Doctrine transport tables).
4. Add health check service aggregator for disk space, permissions, PHP extensions.

## Considerations
- Ensure actions run idempotently and surface errors in UI.
- For shared hosting, avoid operations requiring shell access (no direct `exec`).
- Provide CLI fallback commands mirroring UI actions for scripting.
