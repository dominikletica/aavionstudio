# Feat: Core Platform (P0 | Scope: XL)

**Status:** Draft – subject to refinement as implementation progresses.  
**Goal:** Establish the foundational Symfony architecture, module system, and runtime services that every other feature depends on.

## Overview
- Symfony 7.3 skeleton enhanced with shared-hosting compat (public webroot + root fallback loader).
- Dual SQLite data stores (`system.brain`, `user.brain`) with Doctrine ORM and custom attach listener.
- Modular service registration: each feature can expose backend services, console commands, routes, and Vue/Stimulus controllers through a structured manifest.
- Unified configuration layer: environment variables defaulted in `.env`, overrides via installer-generated `.env.local.php`.

## Architecture Components
1. **Kernel & Bundles**
   - Base bundles: FrameworkBundle, Doctrine (ORM, Migrations), Security, Messenger, RateLimiter, Translation, Serializer, Monolog, Notifier.
   - Custom service tags to auto-register module providers (e.g., `aavion.module`).
2. **Module Discovery**
   - Directory `modules/<slug>/module.php` returns a manifest:
     ```php
     return new ModuleManifest(
         name: 'Version Manager',
         priority: 100,
         services: 'modules/version-manager/config/services.php',
         routes: 'modules/version-manager/config/routes.yaml',
         adminNavigation: [
             new AdminLink('Version Manager', route: 'aavion_admin_version_manager'),
         ],
     );
     ```
   - Module loader compiles manifests at cache warmup; supports enable/disable flags stored in `system.brain`.
3. **Installer Pipeline**
   - Health checks: PHP version, required extensions, writable directories, SQLite availability.
   - Steps: configure base settings → create admin user → generate secrets → run migrations.
   - Persist installation state to prevent reruns.
4. **Fallback Bootstrap**
   - Root `index.php` delegates to `public/index.php`, optionally routing through `?route=` param.
   - Security warning banner in installer when rewrite/docroot not enforced.

## Data Layer
- Doctrine naming consistent with ULID primary keys.
- Table naming convention: `app_<domain>` (`app_project`, `app_entity`, `app_entity_version`, `app_draft`, `app_schema`, `app_template`, `app_relation`, `app_api_key`, `app_user`, `app_log`).
- Doctrine type overrides: register `ulid`, `uuid`, JSON column helper.
- Migrations must include triggers or checks for materialized path integrity.
- Default seeds live under `config/app/` (`system_settings.php`, `projects.php`) so baseline data can evolve without rewriting migrations.

## Installer UX Flow
1. **Welcome & Diagnostics**
   - Display rewrite status (detected via request rewrite headers + CLI fallback probe).
   - Validate PHP extensions (`intl`, `sqlite3`, `fileinfo`, `json`, `mbstring`, `ctype`), file permissions (`var/`, `var/uploads/`, `var/snapshots/`, `var/themes/`, `public/assets/`), and minimum PHP version (8.2).
   - Provide actionable remediation hints and copy-paste snippets for `.htaccess` / nginx / IIS adjustments.
2. **Environment Configuration**
   - Collect instance name, default locale, timezone, email settings (optional), and secure cookie domain.
   - Persist values into generated `.env.local.php`, never asking operators to edit files manually.
3. **Database & Storage Setup**
   - Create or connect to SQLite files under `var/` with configurable path override; run migration dry-run to ensure file permissions.
   - Offer optional data directory relocation (e.g., `DATA_PATH`) with UI-based picker.
4. **Administrator Creation**
   - Collect email, password (strength meter), locale preference.
   - Enforce password policy (min length, complexity toggle, ban common passwords list).
5. **Summary & Finalisation**
   - Present checklist of performed actions, surface warnings (e.g., running in root compatibility mode) with links to hardening docs.
   - Trigger cache warmup + module manifest compilation before redirecting to `/admin/login`.

## Diagnostics Reference
- `extends_php_version`: warn when below 8.3 and link to upgrade guide.
- `ext_intl`, `ext_sqlite3`, `ext_fileinfo`, `ext_mbstring`, `ext_json`: mark as critical failures.
- `rewrite_enabled`: info badge when failing; blocks progression unless user acknowledges risk banner.
- Writable checks: `var_root_writable`, `var_uploads_writable`, `var_snapshots_writable`, `var_themes_writable`, `public_assets_writable`; surface last error message and remediation tips.
- `app_secret_strength`: ensure generated ULID is stored securely; re-roll button in UI.
- All diagnostics emit machine-readable codes for potential automation (e.g., `DIAG-REWRITE-MISSING`).

## Module Integration
- Admin UI nav composed from module manifests (sorted by priority).
- Routes isolated per module under `/admin/<module-slug>`; fallback to catch-all for modules without UI.
- AssetMapper pipeline loads Stimulus controllers from `modules/*/assets/controllers`.

### Module Manifest Contract
- Required fields: `name`, `priority`, `services`, `routes`, `capabilities`.
- Optional integrations:
  - `navigation`: array of admin navigation entries with capability requirements.
  - `themeSlots`: expose Twig block aliases for theme packs.
  - `scheduler`: cron-like definitions consumed by maintenance module.
- Pseudocode for loader:
  ```php
  final class ModuleRegistry
  {
      /** @var ModuleManifest[] */
      private array $manifests = [];

      public function register(ModuleManifest $manifest): void
      {
          $this->manifests[$manifest->priority][] = $manifest;
      }

      public function boot(ContainerBuilder $container): void
      {
          krsort($this->manifests); // highest priority first
          foreach ($this->manifests as $manifests) {
              foreach ($manifests as $manifest) {
                  $this->loadServices($container, $manifest->services);
                  $this->loadRoutes($manifest->routes);
                  $this->applyNavigation($manifest->navigation);
              }
          }
      }
  }
  ```
- Manifest validation runs during cache warmup; failures bubble up with clear file references.

## Implementation Phases
1. Build module manifest contracts + loader (hard dependencies: DI container & cache warmup).
2. Implement installer controller + steps, persisting state in `system.brain`.
3. Scaffold Doctrine migrations for core tables, including attach listener wiring & connection tests.
4. Provide root bootstrap file with diagnostics + manual security checklist.

## Root Compatibility Hardening
- When rewrite missing, enforce `Options -Indexes` and deny direct access to sensitive directories via generated `.htaccess`.
- Surface banner inside admin until rewrite-only mode confirmed; link to documentation describing risk of vendor/config exposure.
- Disable public snapshot caching in compatibility mode to avoid leaking template paths.

## Risks & Mitigations
- **Shared hosting constraints:** Document manual configuration, surface warnings in installer.
- **Module misconfiguration:** Validate manifests during cache warmup; fail fast with actionable errors.
- **SQLite locking:** Use `PRAGMA busy_timeout`; consider DB-level locks via Symfony Lock when publishing.

## Dependencies & Interfaces
- Depends on: Doctrine ORM, Messenger (later phases), Symfony Cache/Lock, Installer UI templates.
- Provides: Module loader service, configuration registry, event dispatcher hooks for other features.

## Decisions (2025-10-30)
- Deploy rewrite-first with root compatibility loader only for hosts lacking docroot control; installer blocks progression until operator acknowledges the risk banner.
- Installer produces `.env.local.php` and stores system settings in `system.brain`, ensuring drop-in packages stay CLI-free for operators.
- Module registry acts as the single source of truth for navigation, theme slots, scheduler hooks, and capability declarations to keep feature enablement deterministic.
