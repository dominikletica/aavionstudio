# Developer Class Map (Draft)

> **Status:** This document tracks callable entry points (services, commands, controllers, Twig components, Stimulus controllers). Keep it up to date as new classes are added or interfaces change.

---

## 1. Symfony Services

| Service ID | Class | Responsibility | Notes |
|------------|-------|----------------|-------|
| `App\Kernel` | `src/Kernel.php` | Application kernel | Uses MicroKernelTrait |
| `App\Doctrine\Middleware\AttachUserDatabaseMiddleware` | `src/Doctrine/Middleware/AttachUserDatabaseMiddleware.php` | Attaches `user.brain` to primary SQLite connection via DBAL middleware and configures pragmas | Sets `PRAGMA busy_timeout`/`foreign_keys` and ensures secondary DB file exists |
| `App\Doctrine\Health\SqliteHealthChecker` | `src/Doctrine/Health/SqliteHealthChecker.php` | Reports connection status for system + user SQLite stores | Used in tests/diagnostics to confirm attachment and busy timeout |
| `App\Installer\DefaultSystemSettings` | `src/Installer/DefaultSystemSettings.php` | Loads shared default settings from `config/app/system_settings.php` | Consumed by migrations/installer to seed baseline values |
| `App\Setup\SetupConfiguration` | `src/Setup/SetupConfiguration.php` | Stores installer form choices (environment, storage, admin) in the session and exposes merged defaults | `freeze()` snapshots wizard data for installer actions so streaming can run without a live HTTP session |
| `App\Setup\SetupConfigurator` | `src/Setup/SetupConfigurator.php` | Persists system settings and project seeds after `bin/init` runs | Called by the installer action executor |
| `App\Setup\SetupEnvironmentWriter` | `src/Setup/SetupEnvironmentWriter.php` | Persists immutable runtime variables (`APP_ENV`, `APP_DEBUG`, `APP_SECRET`, `DATABASE_URL`, DSNs) to `.env.local` and prepares storage directories | Invoked by the setup action executor before `bin/init`; computes SQLite paths from the chosen storage root |
| `App\Setup\SetupHelpLoader` | `src/Setup/SetupHelpLoader.php` | Loads contextual help entries from `docs/setup/help.<locale>.json`, merges locale fallbacks, and preserves `target` metadata for field-level tooltips | Used by installer controller/templates |
| `App\Setup\SetupPayloadBuilder` | `src/Setup/SetupPayloadBuilder.php` | Serialises storage, administrator, system-settings, and project data into the installer payload JSON | Payload lives at `var/setup/runtime.json`; consumed by `bin/init --setup` / `app:setup:seed` |
| `App\Settings\SystemSettings` | `src/Settings/SystemSettings.php` | Lazy loads settings from `app_system_setting` with fallback to defaults | Shared by error resolver, profile registry, etc. |
| `App\Security\User\UserProfileFieldRegistry` | `src/Security/User/UserProfileFieldRegistry.php` | Supplies configurable profile field metadata & normalisation helpers | Injected into the admin user form and manager |
| `App\Translation\DebugTranslator` | `src/Translation/DebugTranslator.php` | Decorates the Symfony translator to surface key-only output in debug mode and respect footer overrides | Decorates `translator`; covered by `tests/Translation/DebugTranslatorTest.php` |
| `App\Translation\CatalogueManager` | `src/Translation/CatalogueManager.php` | Merges translations from active theme, enabled modules, base theme, and system catalogues with caching | Consumed by `DebugTranslator`; covered by `tests/Translation/CatalogueManagerTest.php` |
| `App\Installer\DefaultProjects` | `src/Installer/DefaultProjects.php` | Provides default project seeds from `config/app/projects.php` | Initial migration inserts `default` project via this helper |
| `App\Installer\Action\ActionExecutor` | `src/Installer/Action/ActionExecutor.php` | Executes installer/updater action chains (zip extraction, console/init calls, lock creation) with streamed output | Used by `/setup/action` controller |
| `App\Controller\Installer\ActionController` | `src/Controller/Installer/ActionController.php` | Streams or buffers installer action output based on configured mode | Snapshots/removes the real session before streaming, swaps in an in-memory session, and logs NDJSON failures; accepts `INSTALLER_ACTION_MODE` |
| `App\Bootstrap\RootEntryPoint` | `src/Bootstrap/RootEntryPoint.php` | Normalises requests that hit the root fallback (`index.php`) and forwards them to `public/index.php` | Sets compatibility flags for installer rewrite diagnostics |
| `App\Error\ErrorPageResolver` | `src/Error/ErrorPageResolver.php` | Resolves project/error-code specific Twig templates with fallback chain | Used by `App\Controller\Error\ErrorController`; behaviour covered by `tests/Controller/Error/ErrorControllerTest.php` |
| `App\Module\ModuleDiscovery` | `src/Module/ModuleDiscovery.php` | Discovers module manifests under `/modules/*/module.php` | Supports drop-in modules without Composer autoload |
| `App\Module\ModuleRegistry` | `src/Module/ModuleRegistry.php` | Provides module manifest lookup/capability aggregation | Hydrated from `app.modules` parameter during boot |
| `App\Module\ModuleStateRepository` | `src/Module/ModuleStateRepository.php` | Reads persisted module enable/metadata flags from database | Optional helper for future enable/disable UI |
| `App\Module\ModuleStateSynchronizer` | `src/Module/ModuleStateSynchronizer.php` | Syncs manifest metadata with `app_module_state` table during kernel boot | Keeps repository URLs/locks up to date |
| `App\Theme\ThemeDiscovery` | `src/Theme/ThemeDiscovery.php` | Discovers theme manifests from `/themes/*/theme.{php,yaml}` | Produces `ThemeManifest` list used during kernel boot |
| `App\Theme\ThemeRegistry` | `src/Theme/ThemeRegistry.php` | Provides theme manifest lookup for tooling | Hydrated from `app.themes` parameter |
| `App\Theme\ThemeStateSynchronizer` | `src/Theme/ThemeStateSynchronizer.php` | Syncs theme metadata into `app_theme_state` during kernel boot | Keeps DB record aligned with manifest info |
| `App\Theme\ThemeStateRepository` | `src/Theme/ThemeStateRepository.php` | Reads stored theme enable/metadata state | Simple helper for future management UI |
| `App\Twig\TemplatePathConfigurator` | `src/Twig/TemplatePathConfigurator.php` | Rebuilds Twig search paths (active theme → modules → base templates) each boot | Injected into kernel during boot |
| `App\Setup\SetupState` | `src/Setup/SetupState.php` | Tracks system/user SQLite paths and setup lock status | Consumed by installer, redirect subscriber, and migration synchroniser |
| `App\Setup\SetupAccessToken` | `src/Setup/SetupAccessToken.php` | Issues and validates session-scoped installer action tokens | Protects `/setup/action` by requiring a wizard session |
| `App\Setup\SetupFinalizer` | `src/Setup/SetupFinalizer.php` | Creates database files, runs pending migrations, and writes the `.setup.lock` marker | Triggered by `/setup/complete` |
| `App\Setup\MigrationSynchronizer` | `src/Setup/MigrationSynchronizer.php` | Applies outstanding migrations automatically after setup is locked | Invoked during kernel boot; logs failures |
| `App\Asset\AssetStateTracker` | `src/Asset/AssetStateTracker.php` | Hashes module/theme asset trees and stores checksum cache in `var/cache/assets-state.json` | Depends on `ModuleRegistry`/`ThemeRegistry` plus kernel dir parameters |
| `App\Asset\AssetPipelineRefresher` | `src/Asset/AssetPipelineRefresher.php` | Clears cache, purges mirrored asset targets, then runs sync → importmap → Tailwind → asset-map before warming cache and persisting state hashes | Depends on `AssetStateTracker`, logger, kernel parameters |
| `App\Asset\StylesheetImportsBuilder` | `src/Asset/StylesheetImportsBuilder.php` | Regenerates `assets/styles/imports.css` combining base tokens, active theme styles, and enabled module styles | Used by asset sync/rebuild pipeline |
| `App\Service\AssetRebuildScheduler` | `src/Service/AssetRebuildScheduler.php` | Orchestrates synchronous/asynchronous rebuilds; dispatches `AssetRebuildMessage` when changes detected | Uses tracker, Messenger bus, pipeline refresher |
| `App\MessageHandler\AssetRebuildMessageHandler` | `src/MessageHandler/AssetRebuildMessageHandler.php` | Messenger handler executing queued asset rebuild jobs | Handles `App\Message\AssetRebuildMessage` |
| `App\EventSubscriber\AssetBootstrapSubscriber` | `src/EventSubscriber/AssetBootstrapSubscriber.php` | Forces an asset rebuild on the first HTTP request when `public/assets` is missing | Autoconfigured kernel request subscriber |
| `App\EventSubscriber\AssetBootstrapSubscriber` | `src/EventSubscriber/AssetBootstrapSubscriber.php` | Rebuilds `public/assets` on-demand before serving HTTP requests when the directory is missing or empty | Calls `AssetRebuildScheduler::runNow(true)` and logs failures |
| `App\EventSubscriber\SetupRedirectSubscriber` | `src/EventSubscriber/SetupRedirectSubscriber.php` | Redirects non-setup HTTP traffic to `/setup` until the setup lock exists | Autoconfigured Kernel request subscriber |
| `App\Security\User\AppUserProvider` | `src/Security/User/AppUserProvider.php` | Doctrine-backed user provider for authentication | Handles status checks, password upgrades, role loading |
| `App\Security\Capability\CapabilityRegistry` | `src/Security/Capability/CapabilityRegistry.php` | Aggregates module-declared capabilities for lookup | Feeds synchronizer and future ACL tooling |
| `App\Security\Capability\CapabilitySynchronizer` | `src/Security/Capability/CapabilitySynchronizer.php` | Persists capability defaults into `app_role_capability` and logs seeding | Invoked during kernel boot |
| `App\Security\Password\PasswordResetTokenManager` | `src/Security/Password/PasswordResetTokenManager.php` | Issues, validates, and purges password reset tokens | Stores selector/verifier hashes in `app_password_reset_token` |
| `App\Security\User\UserInvitationManager` | `src/Security/User/UserInvitationManager.php` | Manages invitation tokens and audit logging | Powers admin onboarding flow |
| `App\Security\Authorization\ProjectMembershipRepository` | `src/Security/Authorization/ProjectMembershipRepository.php` | Reads/writes project membership assignments | Backing store for project capability voters |
| `App\Security\Authorization\RoleCapabilityResolver` | `src/Security/Authorization/RoleCapabilityResolver.php` | Resolves capability inheritance for roles | Used by project capability voter |
| `App\Security\Authorization\ProjectCapabilityVoter` | `src/Security/Authorization/ProjectCapabilityVoter.php` | Evaluates project-scoped capability requirements | Registered as security voter |
| `App\Security\User\UserCreator` | `src/Security/User/UserCreator.php` | Creates users (e.g. invitation onboarding) with hashed passwords and audit logging | Consumed by invitation acceptance; functional coverage in `tests/Controller/Security/InvitationAcceptControllerTest.php` |
| `App\Security\User\UserAdminManager` | `src/Security/User/UserAdminManager.php` | Lists/updates users for admin UI, persists role assignments, records audit logs | Exercised via `tests/Controller/Admin/UserControllerTest.php` |
| `App\Security\Api\ApiKeyManager` | `src/Security/Api/ApiKeyManager.php` | Issues, lists, and revokes API keys with hashed secrets and audit logging | Unit coverage in `tests/Security/Api/ApiKeyManagerTest.php` |
| `App\Security\Audit\SecurityAuditRepository` | `src/Security/Audit/SecurityAuditRepository.php` | Reads audit log entries with filtering and context decoding | Used by admin audit viewer (`tests/Controller/Admin/SecurityAuditControllerTest.php`) |
| `App\Project\ProjectRepository` | `src/Project/ProjectRepository.php` | Lists core projects for admin tooling | Consumed by admin user controller |

### Suggested Structure
- **Core Services:** Module loader, schema registry, draft manager, snapshot manager, resolver engine, media storage.
- **Infrastructure Services:** Cache, lock, messenger transports.
- **Integrations:** Exporter manager, webhook dispatcher, backup manager.

For each service added to `config/services.yaml` or module manifests, document:
- Constructor signature (important dependencies).
- Key methods and event hooks.
- Related tests (link to PHPUnit class).

---

## 2. Controllers

| Route Name | Class | Description | Module |
|------------|-------|-------------|--------|
| `framework.error_controller` | `src/Controller/Error/ErrorController.php` | Project-aware HTML error pages with debug-aware diagnostics and Symfony fallback | Core; covered by `tests/Controller/Error/ErrorControllerTest.php` |
| `_theme_demo` | `src/Controller/DemoController.php` | Renders the UI component showcase/demo route for theming work | Covered by `tests/Controller/DemoControllerTest.php` |
| `_theme_demo_tip` | `src/Controller/DemoController.php` | Turbo-frame fragment serving rotating theming tips | Covered by `tests/Controller/DemoControllerTest.php` |
| `app_login` | `src/Controller/Security/LoginController.php` | Handles sign-in form | Core |
| `app_password_forgot` | `src/Controller/Security/PasswordResetController.php` | Password reset request | Core |
| `app_password_reset` | `src/Controller/Security/PasswordResetController.php` | Password reset confirmation | Core |
| `app_invitation_accept` | `src/Controller/Security/InvitationAcceptController.php` | Handles invitation acceptance redirect | Core; covered by `tests/Controller/Security/InvitationAcceptControllerTest.php` |
| `app_debug_locale` | `src/Controller/Debug/LocaleController.php` | Debug-only endpoint toggling locale overrides and key display for the footer switcher | Core (debug); covered by `tests/Controller/Debug/LocaleControllerTest.php` |
| `admin_users_index` | `src/Controller/Admin/UserController.php` | Lists users for admin management | Core |
| `admin_users_edit` | `src/Controller/Admin/UserController.php` | Edits user profile/roles | Core |
| `admin_users_api_keys_revoke` | `src/Controller/Admin/UserController.php` | Revokes an API key for a user | Core |
| `admin_users_password_reset` | `src/Controller/Admin/UserController.php` | Sends password reset email for a user | Core |
| `admin_security_audit` | `src/Controller/Admin/SecurityAuditController.php` | Lists security audit log entries with filters | Core |
| `admin_project_capability_probe` | `src/Controller/Admin/ProjectCapabilityProbeController.php` | Simple endpoint verifying project capability access | Core |
| `admin_api_keys_list` | `src/Controller/Admin/AdminApiKeyController.php` | REST endpoint listing API keys for a user | Core |
| `admin_api_keys_create` | `src/Controller/Admin/AdminApiKeyController.php` | Creates API keys via REST endpoint | Core |
| `admin_api_keys_revoke` | `src/Controller/Admin/AdminApiKeyController.php` | Revokes API keys via REST endpoint | Core |
| `admin_assets_overview` | `src/Controller/Admin/SystemAssetsController.php` | Admin UI for queuing or running asset pipeline rebuilds | Core |
| `admin_assets_rebuild` | `src/Controller/Admin/SystemAssetsController.php` | POST endpoint backing the rebuild forms | Core |
| `app_frontend` | _TBD_ | Catch-all frontend controller | Core |
| `app_admin_dashboard` | _TBD_ | Admin landing page | Core |
| ... |  |  |  |

---

## 3. Console Commands

| Command | Class | Description | Dependencies |
|---------|-------|-------------|--------------|
| `app:snapshot:rebuild` | _TBD_ | Rebuild published snapshots | SnapshotManager |
| `app:backup:run` | _TBD_ | Create backup archive | BackupManager |
| `app:api-key:issue` | `src/Command/IssueApiKeyCommand.php` | Issue API key for a user and print the secret | `ApiKeyManager`, `AppUserRepository` |
| `app:assets:sync` | `src/Command/SyncDiscoveredAssetsCommand.php` | Clears the cache, respawns itself, then mirrors `modules/*/assets` and `themes/*/assets` into the core `assets/` tree so builds/tests see new manifests on the first run | `ModuleRegistry`, `ThemeRegistry`, `%kernel.project_dir%`, `Filesystem`, `Symfony\Component\Process\Process` |
| `app:assets:rebuild` | `src/Command/RebuildAssetsCommand.php` | Runs or queues the full asset pipeline rebuild; supports `--force` and `--async` | `AssetRebuildScheduler` |
| `app:setup:seed` | `src/Command/SetupSeedCommand.php` | Reads installer payload JSON and seeds the first administrator post `bin/init --setup` | `UserCreator`, DBAL connection, Filesystem |
| ... |  |  |  |

---

## 4. Twig Components & Extensions

| Identifier | Class/Template | Purpose |
|------------|----------------|---------|
| `App\Twig\Components\NavSidebar` | _TBD_ | Render admin navigation |
| Twig Extension | _TBD_ | Custom filters/functions for schema rendering |

Document:
- Component props/context.
- Slots or blocks used.
- Template paths (`templates/components/...`).

---

## 5. Stimulus Controllers

| Controller Name | File | Description |
|-----------------|------|-------------|
| `codemirror` | `assets/controllers/codemirror_controller.js` | Enhances textareas with CodeMirror editor |
| `hello` | `assets/controllers/hello_controller.js` | Example scaffold (remove/replace) |
| ... |  |  |

---

## 6. Modules Registry (Future Entries)

Record module manifest classes/files and their contributions:

| Module | Manifest Path | Services | Routes | Assets |
|--------|---------------|----------|--------|--------|
| Exporter | `modules/exporter/module.php` | `modules/exporter/config/services.php` | `modules/exporter/config/routes.yaml` | `modules/exporter/assets/...` |
| Maintenance | ... | ... | ... | ... |

---

## Maintenance Tips
- Whenever adding a new service/controller/command, update this map.
- Reference canonical developer or user docs when available instead of transient notes.
- Include test class references to ease traceability (`tests/...`).
- Mark deprecated entries clearly when refactoring.

This document is meant to evolve alongside the codebase—treat it as a living index for developers to quickly discover callables without grepping through the project.
