# Developer Manual

Status: Draft  
Updated: 2025-10-31

Welcome to the technical companion for aavion Studio. This manual outlines the development workflow, architecture conventions, and references to subsystem guides under `docs/dev/sections/`.

---

## 1. Onboarding & environment setup

1. **Clone & Bootstrap**
   ```bash
   git clone https://github.com/dominikletica/aavionstudio.git
   cd aavionstudio
   bin/init dev
   ```
   The script installs Composer dependencies, refreshes importmap assets, compiles Tailwind CSS, prepares SQLite databases, ensures Messenger transports, warms caches, and generates `.env.local` for the selected environment. Switch contexts later with `bin/init prod` or `bin/init test`.

2. **Environment Variables**
   - `.env` holds shared defaults. The init script regenerates `.env.local` with `APP_ENV`, `APP_DEBUG`, and `APP_SECRET` tailored to the chosen environment (prod receives a random secret by default).
   - Rerun `bin/init <env>` whenever you need to switch contexts; existing secrets in `.env.local` are preserved unless you export a new `APP_SECRET` before running the script.
   - Additional overrides still belong in `.env.local` / `.env.local.php`. PHPUnit forces `APP_ENV=test` at runtime, so dev installs retain test coverage.

3. **Local Web Server**
   - `symfony serve` or `php -S localhost:8000 -t public`
   - For root fallback testing, set `APP_FORCE_ROOT_ENTRY=1` and hit `index.php`.
   - The setup diagnostics flag compatibility mode when requests arrive via the root loader—fix docroot/rewrite configuration to clear the warning before going live.
   - Apache/IIS fallback files (`.htaccess`, `web.config`) ship with the repository, but production installs should point the web server directly at `public/`.
   - Access the setup wizard at `http://localhost:8000/setup` to iterate through diagnostics and seed configuration. Each step now renders Symfony forms (environment, storage, administrator) that persist selections in the session before `bin/init` runs—handy for exercising the installer without touching `.env.local` manually. The environment writer merges your overrides into `.env.local` atomically right before the init script executes; `bin/init --setup --payload=var/setup/runtime.json` is then invoked automatically to seed the first administrator via `app:setup:seed`.

---

## 2. Project directory map

| Path | Purpose |
|------|---------|
| `src/` | Symfony application code (controllers, services, modules) |
| `modules/` | Optional feature modules (service manifests, assets, templates) |
| `assets/` | Tailwind styles, Stimulus controllers, JS modules |
| `public/` | Webroot (front controller, built assets) |
| `config/app/` | Default seeds for system settings, projects, modules |
| `var/` | Cache, logs, SQLite databases (`var/system.brain`, `var/user.brain`), snapshots, uploads, backups (installer diagnostics flag missing/writable directories) |
| `docs/` | Documentation (developer, user, codex notes) |
| `modules/` | Drop-in feature modules discovered via `module.php` manifests (no Composer autoload required, support `.aavmodule` bundles) |

---

## 3. Core workflows

- **Coding Standards**
  - PHP: PSR-12, strict types when applicable, service autowiring/autoconfigure.
  - Frontend: Tailwind utility classes, Stimulus controllers (`snake_controller.js`), AssetMapper imports.
  - Translations: English only (`admin`, `validators`, etc.).

- **Testing**
  - PHPUnit for unit/integration; plan to add Panther/Cypress for UI flows.
  - Use SQLite in-memory for unit tests; attach `user.brain` if needed.
  - Run `php bin/phpunit --coverage-text` before PRs.

- **Documentation Discipline**
  - Update module/feature drafts in `docs/codex/notes/` as behaviour changes.
  - Keep class map (`docs/dev/classmap.md`) in sync with new services/commands/components.
  - Log session progress in `docs/codex/WORKLOG.md`.
- **Module Packaging**
  - Each module ships a `module.php` manifest (returning `ModuleManifest`) and optional config/assets under the same directory.
  - Release bundles use the `.aavmodule` extension and include a `repository` URL for update scans (mirrors theme update behaviour).
  - Drop modules into `/modules/<slug>/` and clear cache; discovery works without Composer autoload.
  - Aggregated capability metadata is exposed via the `app.capabilities` container parameter (derived from enabled module manifests).
- **Release Packaging**
  - Generate deployable archives with `bin/release <env> <version> <channel>`.
  - See [`docs/dev/sections/workflows/release.md`](sections/workflows/release.md) for details.
- **Automation & Access**
  - Issue API keys for integrations with `php bin/console app:api-key:issue <user>`; see [`docs/dev/sections/security/api-keys.md`](sections/security/api-keys.md) for usage details.
- **Audit Trail**
  - Inspect security events via `/admin/security/audit`; implementation notes live in [`docs/dev/sections/security/audit-log.md`](sections/security/audit-log.md).

---

## 4. Reference index

- **Contributor Guidelines:** [`AGENTS.md`](../../AGENTS.md)
- **Worklog & Session Notes:** [`docs/codex/WORKLOG.md`](../codex/WORKLOG.md)
- **Environment Recap:** [`docs/codex/ENVIRONMENT.md`](../codex/ENVIRONMENT.md)
- **Concept Outline & Feature Drafts:** [`docs/codex/notes/`](../codex/notes/)
- **Developer Sections:** `docs/dev/sections/` (add guides per subsystem)
- **Class Map:** [`docs/dev/classmap.md`](classmap.md)
- **Security Guides:** [`docs/dev/sections/security/`](sections/security/) – invitation onboarding, user management UI, upcoming capability registry deep dives.
- **UI & Theming:** [`docs/dev/sections/ui/templates-and-themes.md`](sections/ui/templates-and-themes.md) – template cascade, partial contracts, theme overrides.

---

## 5. Open tasks & roadmap

Consult the live TODOs and roadmap in `docs/codex/WORKLOG.md`. Each feature has a dedicated draft outlining architecture, dependencies, and testing strategy.

---

## 6. Contributing guides (to be expanded)

- **Subsystem Guides:** Document specifics under `docs/dev/sections/` (e.g., resolver, snapshot manager, module system).
- **API Reference:** Maintain OpenAPI specs and usage notes once the read/write APIs stabilise.
- **Deployment Recipes:** Capture shared-hosting vs container deployment steps.

Keep this manual updated as new tooling, scripts, or architectural decisions emerge.
