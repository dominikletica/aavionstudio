# Developer Class Map (Draft)

> **Status:** This document tracks callable entry points (services, commands, controllers, Twig components, Stimulus controllers). Keep it up to date as new classes are added or interfaces change.

---

## 1. Symfony Services

| Service ID | Class | Responsibility | Notes |
|------------|-------|----------------|-------|
| `App\Kernel` | `src/Kernel.php` | Application kernel | Uses MicroKernelTrait |
| `App\Doctrine\Listener\AttachUserDatabaseListener` | `src/Doctrine/Listener/AttachUserDatabaseListener.php` | Attaches `user.brain` to primary SQLite connection, configures pragmas | Sets `PRAGMA busy_timeout`/`foreign_keys` and ensures secondary DB file exists |
| `App\Doctrine\Health\SqliteHealthChecker` | `src/Doctrine/Health/SqliteHealthChecker.php` | Reports connection status for system + user SQLite stores | Used in tests/diagnostics to confirm attachment and busy timeout |
| `App\Installer\DefaultSystemSettings` | `src/Installer/DefaultSystemSettings.php` | Loads shared default settings from `config/app/system_settings.php` | Consumed by migrations/installer to seed baseline values |
| `App\Installer\DefaultProjects` | `src/Installer/DefaultProjects.php` | Provides default project seeds from `config/app/projects.php` | Initial migration inserts `default` project via this helper |
| `App\Bootstrap\RootEntryPoint` | `src/Bootstrap/RootEntryPoint.php` | Normalises requests that hit the root fallback (`index.php`) and forwards them to `public/index.php` | Sets compatibility flags for installer rewrite diagnostics |
| `App\Module\ModuleDiscovery` | `src/Module/ModuleDiscovery.php` | Discovers module manifests under `/modules/*/module.php` | Supports drop-in modules without Composer autoload |
| `App\Module\ModuleRegistry` | `src/Module/ModuleRegistry.php` | Provides module manifest lookup/capability aggregation | Hydrated from `app.modules` parameter during boot |
| `App\Module\ModuleStateRepository` | `src/Module/ModuleStateRepository.php` | Reads persisted module enable/metadata flags from database | Optional helper for future enable/disable UI |
| `App\Module\ModuleStateSynchronizer` | `src/Module/ModuleStateSynchronizer.php` | Syncs manifest metadata with `app_module_state` table during kernel boot | Keeps repository URLs/locks up to date |
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
| `app_login` | `src/Controller/Security/LoginController.php` | Handles sign-in form | Core |
| `app_password_forgot` | `src/Controller/Security/PasswordResetController.php` | Password reset request | Core |
| `app_password_reset` | `src/Controller/Security/PasswordResetController.php` | Password reset confirmation | Core |
| `app_invitation_accept` | `src/Controller/Security/InvitationAcceptController.php` | Handles invitation acceptance redirect | Core; covered by `tests/Controller/Security/InvitationAcceptControllerTest.php` |
| `admin_users_index` | `src/Controller/Admin/UserController.php` | Lists users for admin management | Core |
| `admin_users_edit` | `src/Controller/Admin/UserController.php` | Edits user profile/roles | Core |
| `admin_users_api_keys_revoke` | `src/Controller/Admin/UserController.php` | Revokes an API key for a user | Core |
| `admin_users_password_reset` | `src/Controller/Admin/UserController.php` | Sends password reset email for a user | Core |
| `admin_security_audit` | `src/Controller/Admin/SecurityAuditController.php` | Lists security audit log entries with filters | Core |
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
- Cross-link to relevant drafts in `docs/codex/notes/`.
- Include test class references to ease traceability (`tests/...`).
- Mark deprecated entries clearly when refactoring.

This document is meant to evolve alongside the codebase—treat it as a living index for developers to quickly discover callables without grepping through the project.
