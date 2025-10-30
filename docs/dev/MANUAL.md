# Developer Manual

Status: Draft  
Updated: 2025-10-29

Welcome to the technical companion for aavion Studio. This manual outlines the development workflow, architecture conventions, and references to subsystem guides under `docs/dev/sections/`.

---

## 1. Onboarding & environment setup

1. **Clone & Bootstrap**
   ```bash
   git clone https://github.com/dominikletica/aavionstudio.git
   cd aavionstudio
   bin/init_repository
   ```
   The script installs Composer dependencies, refreshes importmap assets, compiles Tailwind CSS, prepares SQLite databases, ensures Messenger transports, and warms caches.

2. **Environment Variables**
   - Default dev config via `.env` / `.env.dev` (`APP_ENV=dev`, `APP_DEBUG=1`, `DATABASE_URL=sqlite:///%kernel.project_dir%/var/system.brain`).
   - `APP_SECRET` ships with a dummy value for local development and tests. The installer will generate a secure secret in `.env.local.php` for production deployments automatically. If you expose dev/test environments publicly, replace the dummy in `.env.*` with your own values.
   - Additional overrides belong in `.env.local` or `.env.local.php`.

3. **Local Web Server**
   - `symfony serve` or `php -S localhost:8000 -t public`
   - For root fallback testing, set `APP_FORCE_ROOT_ENTRY=1` and hit `index.php`.

---

## 2. Project directory map

| Path | Purpose |
|------|---------|
| `src/` | Symfony application code (controllers, services, modules) |
| `modules/` | Optional feature modules (service manifests, assets, templates) |
| `assets/` | Tailwind styles, Stimulus controllers, JS modules |
| `public/` | Webroot (front controller, built assets) |
| `data/` | Snapshots, uploads, backups |
| `var/` | Cache, logs, SQLite databases (`system.brain`, `user.brain`) |
| `docs/` | Documentation (developer, user, codex notes) |

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
- **Release Packaging**
  - Generate deployable archives with `bin/release <env> <version> <channel>`.
  - See [`docs/dev/sections/workflows/release.md`](sections/workflows/release.md) for details.

---

## 4. Reference index

- **Contributor Guidelines:** [`AGENTS.md`](../../AGENTS.md)
- **Worklog & Session Notes:** [`docs/codex/WORKLOG.md`](../codex/WORKLOG.md)
- **Environment Recap:** [`docs/codex/ENVIRONMENT.md`](../codex/ENVIRONMENT.md)
- **Concept Outline & Feature Drafts:** [`docs/codex/notes/`](../codex/notes/)
- **Developer Sections:** `docs/dev/sections/` (add guides per subsystem)
- **Class Map:** [`docs/dev/classmap.md`](classmap.md)

---

## 5. Open tasks & roadmap

Consult the live TODOs and roadmap in `docs/codex/WORKLOG.md`. Each feature has a dedicated draft outlining architecture, dependencies, and testing strategy.

---

## 6. Contributing guides (to be expanded)

- **Subsystem Guides:** Document specifics under `docs/dev/sections/` (e.g., resolver, snapshot manager, module system).
- **API Reference:** Maintain OpenAPI specs and usage notes once the read/write APIs stabilise.
- **Deployment Recipes:** Capture shared-hosting vs container deployment steps.

Keep this manual updated as new tooling, scripts, or architectural decisions emerge.
