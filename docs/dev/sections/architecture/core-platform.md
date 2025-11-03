# Core Platform Architecture

Status: Draft  
Updated: 2025-10-30

This guide summarises the core Symfony platform put in place during Roadmap Step 2. It links the high-level plans from `docs/codex/notes/feat-core-platform.md` to the concrete implementation now present in the codebase.

## Kernel & Module System

- **Kernel (`src/Kernel.php`)** bootstraps module manifests before services/routes load. It:
  - Discovers manifests under `modules/*/module.php` via `ModuleDiscovery`.
  - Persists manifest metadata into container parameters (`app.modules`, `app.capabilities`).
  - Imports module service and route configuration for enabled manifests only.
  - Synchronises manifest state into `app_module_state` / `app_theme_state` via dedicated synchronisers.
- **Module manifest contract** (`App\Module\ModuleManifest`) supports services, routes, navigation, repository metadata, human-friendly descriptions, and capability declarations. Errors during discovery produce synthetic manifests with error metadata so diagnostics can surface the issue without fatal failure.
- **Module registry** (`App\Module\ModuleRegistry`) exposes enabled manifests and capability metadata for later features (navigation, access control, etc.).
- **Theme discovery** mirrors the module flow via `App\Theme\ThemeDiscovery`/`ThemeRegistry`/`ThemeStateSynchronizer`; manifests live in `/themes/*/theme.{php,yaml}` and the locked `base` theme (slug `base`) seeds `app_theme_state` as the default asset anchor.
- **Twig template cascade** is reconfigured on boot by `App\Twig\TemplatePathConfigurator`, ensuring lookup order Active Theme → enabled modules (priority desc, slug asc) → base templates.

## Installer Pipeline

- **Controller**: `App\Controller\Installer\InstallerController` renders `/setup` with a multi-step wizard (diagnostics → environment → storage → admin → summary).
- **Diagnostics**:
  - PHP extension checks with remediation hints for each required extension (intl, sqlite3, fileinfo, json, mbstring, ctype).
  - SQLite health report using `SqliteHealthChecker` verifying primary/secondary database paths, attach status, and busy timeout.
  - Rewrite status detection that differentiates between rewrite-first, compatibility fallback, and forced compatibility mode (flags set by `RootEntryPoint`).
  - Filesystem checks covering `var/*` directories and `public/assets` to ensure write permissions exist for caching, logs, snapshots, uploads, themes, and asset builds.
- **Seeds**: Default system settings (`config/app/system_settings.php`) and projects (`config/app/projects.php`) are surfaced in environment/storage steps so operators understand pre-populated data.
- **Provisioning & lock-in**:
  - Non-setup routes are automatically redirected to `/setup` until the `.setup.lock` marker exists (enforced by `SetupRedirectSubscriber`).
  - The summary step exposes a "Finalize installation" action that uses `SetupFinalizer` to create database files, execute outstanding Doctrine migrations, and drop a `.setup.lock` marker under `var/`.
  - Once locked, `/setup` returns a 404 and `MigrationSynchronizer` keeps schemas current by applying pending migrations during future kernel boots.
- Functional coverage lives in `tests/Controller/InstallerControllerTest.php`.

## Database & Doctrine

- Doctrine connects to the primary `var/system.brain` SQLite database. `AttachUserDatabaseMiddleware` attaches `var/user.brain` on connect, enables `PRAGMA busy_timeout` (configurable via `SQLITE_BUSY_TIMEOUT_MS` env or the container parameter fallback), and ensures `PRAGMA foreign_keys` is set.
- Initial migration `Version20251030000100` provisions system tables (`app_project`, `app_schema`, `app_template`, `app_user`, `app_api_key`, `app_log`, `app_module_state`, `app_theme_state`, `app_system_setting`) plus content tables in the attached database namespace (`user_brain.app_entity`, `app_entity_version`, `app_draft`, `app_relation`).
- Seeds insert default projects and system settings while respecting existing records (using `INSERT OR IGNORE`).
- Health checks and unit tests (`tests/Doctrine/*`) verify listener behaviour and sqlite attachment.

## Root Compatibility Loader

- `index.php` at the repository root delegates to `public/index.php` via `App\Bootstrap\RootEntryPoint`.
- The entry point:
  - Normalises incoming `route` query parameters and PATH_INFO to mimic rewrite routing.
  - Sets diagnostic flags (`AAVION_ROOT_ENTRY*`) consumed by the installer to warn about compatibility mode.
  - Rewrites `REQUEST_URI`, `QUERY_STRING`, and `SCRIPT_FILENAME` to mimic normal front controller behaviour.
- Apache/IIS fallback files (`.htaccess`, `public/.htaccess`, `web.config`, `public/web.config`) ship with the repository. They block access to sensitive directories and funnel requests to the public front controller when rewrite or document-root control is limited.

## Tooling & Release Packaging

- `bin/init` still orchestrates dependency installation, asset builds, messenger transport setup, and cache warmup for local environments. Database provisioning now happens via the setup wizard so production builds stay cold.
- `bin/release` stages a clean copy of the repository (excluding build artifacts, vendor, tests, docs), skips `bin/init`, prunes any stray runtime directories, and zips the contents into `build/aavionstudio-<version>-<channel>.zip`. Packages remain free of caches, databases, and compiled assets so setup can perform first-run initialisation. A `release.json` metadata file is generated for runtime version introspection during packaging.
- `AssetBootstrapSubscriber` runs on early HTTP requests and triggers `app:assets:rebuild --force` via `AssetRebuildScheduler` whenever `public/assets` is missing or empty, preventing Twig/layout errors after fresh deploys.
- Documentation for operator workflows lives under `docs/dev/sections/workflows/release.md`; this guide focuses on how the release script ties into the core architecture.

## Security & Access Control Notes (Step 3 kick-off)

- Security configuration now uses `App\Security\User\AppUserProvider` + `AppUserStatusChecker` with form login, remember-me tokens stored in `app_remember_me_token`, and sliding-window throttling.
- Login UI lives in `templates/security/login.html.twig`; controllers in `src/Controller/Security/LoginController.php`.
- Database schema additions (roles, project memberships, audit log, password reset tokens) are introduced in `migrations/Version20251031000100.php` and tested via `tests/Security/AppUserProviderTest.php` / `tests/Security/Password/PasswordResetTokenManagerTest.php`.
- Password reset flow uses `PasswordResetTokenManager`, `/password/forgot` + `/password/reset/{selector}` controllers, Twig views, and email templates.
- Invitation backend (`UserInvitationManager`, `app_user_invitation`) seeds invitation tokens with audit entries; UI hooks will integrate later.
- Project membership repository (`ProjectMembershipRepository`) abstracts `app_project_user` for upcoming voters and admin UI.
- Project capability voter (`ProjectCapabilityVoter`) combines global roles, membership roles, and explicit permissions when authorization checks require project context.
- Capability registry integration, per-project voters, admin UI, and API key flows will build upon this foundation in subsequent milestones.

## Next Steps

- Roadmap Step 3 continues with capability seeding, project access voters, admin user management interfaces, and API key enforcement.
- When new subsystems land (resolver, snapshot manager, etc.), add dedicated guides within `docs/dev/sections/architecture/` and cross-link from this overview.

Keep this document aligned with code changes—anything impacting installer behaviour, module discovery, or release tooling should be reflected here to keep onboarding smooth for contributors.
