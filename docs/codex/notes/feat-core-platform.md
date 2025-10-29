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

## Module Integration
- Admin UI nav composed from module manifests (sorted by priority).
- Routes isolated per module under `/admin/<module-slug>`; fallback to catch-all for modules without UI.
- AssetMapper pipeline loads Stimulus controllers from `modules/*/assets/controllers`.

## Implementation Phases
1. Build module manifest contracts + loader (hard dependencies: DI container & cache warmup).
2. Implement installer controller + steps, persisting state in `system.brain`.
3. Scaffold Doctrine migrations for core tables, including attach listener wiring & connection tests.
4. Provide root bootstrap file with diagnostics + manual security checklist.

## Risks & Mitigations
- **Shared hosting constraints:** Document manual configuration, surface warnings in installer.
- **Module misconfiguration:** Validate manifests during cache warmup; fail fast with actionable errors.
- **SQLite locking:** Use `PRAGMA busy_timeout`; consider DB-level locks via Symfony Lock when publishing.

## Dependencies & Interfaces
- Depends on: Doctrine ORM, Messenger (later phases), Symfony Cache/Lock, Installer UI templates.
- Provides: Module loader service, configuration registry, event dispatcher hooks for other features.
