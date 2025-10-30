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
| `app_frontend` | _TBD_ | Catch-all frontend controller | Core |
| `app_admin_dashboard` | _TBD_ | Admin landing page | Core |
| ... |  |  |  |

---

## 3. Console Commands

| Command | Class | Description | Dependencies |
|---------|-------|-------------|--------------|
| `app:snapshot:rebuild` | _TBD_ | Rebuild published snapshots | SnapshotManager |
| `app:backup:run` | _TBD_ | Create backup archive | BackupManager |
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
