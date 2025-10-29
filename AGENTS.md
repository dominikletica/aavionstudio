# Repository Guidelines

## Project Structure & Module Organization
- `src/` holds Symfony PHP code (controllers, services, domain logic). Follow PSR-4 namespaces under `App\`.
- `assets/` contains frontend sources. Place Stimulus controllers under `assets/controllers/` and Tailwind/CSS modules in `assets/styles/`.
- `config/` stores framework configuration. Keep environment-specific overrides in `.env.local*` files only.
- `templates/` hosts Twig views; `public/` exposes built assets and the front controller (`index.php`).
- `tests/` mirrors `src/` for PHPUnit suites (unit, integration, end-to-end).
- `.codex/` is reserved for reusable tooling/snippets. Canonical meta-docs now live under `docs/codex/`.
- Documentation hubs: developer content in `docs/dev/`, user-facing manuals in `docs/user/`, shared worklog/notes in `docs/codex/`.

## Build, Test, and Development Commands
- `composer install` – install PHP dependencies and confirm required extensions (`ext-intl`, `ext-sqlite3`, `ext-fileinfo`).
- `php bin/console tailwind:build` – compile Tailwind CSS via Symfony Tailwind Bundle.
- `php bin/console asset-map:compile` – refresh AssetMapper output (JS entrypoints & importmap pins).
- `php bin/console doctrine:migrations:diff` / `php bin/console doctrine:migrations:migrate` – generate and apply schema migrations.
- `php bin/phpunit` – execute the complete PHPUnit 12 test suite; append `--coverage-text` for quick coverage feedback.

## Coding Style & Naming Conventions
- PHP adheres to PSR-12: four-space indentation, `declare(strict_types=1);` where applicable, snake_case for YAML keys.
- Twig templates use lowercase, hyphenated filenames (`layouts/base.html.twig`).
- Stimulus controllers follow `snake_controller.js` naming and register via the Symfony loader.
- Apply automated formatters when available (e.g. `php-cs-fixer`); otherwise rely on IDE PSR-12 formatting and composer-normalized JSON.
- Repository text (code comments, docs, commits) must remain English, even when collaboration happens in other languages.
- Maintainers keep `docs/dev/classmap.md` up to date with every callable (services, commands, Twig components) so contributors can locate references without codewide searches.

## Testing Guidelines
- Use PHPUnit 12 with namespaces mirroring production code (`tests/Unit/App/...`, `tests/Integration/App/...`).
- Test methods follow `testSomething()` or `it_should_doSomething()` naming for clarity.
- Keep tests deterministic: seed fixtures via Doctrine, avoid external services, and clean up database state.
- Run `php bin/phpunit --coverage-text` before opening a PR; ensure new logic is covered or document gaps in `docs/codex/WORKLOG.md`.

## Commit & Pull Request Guidelines
- Write present-tense imperative commit messages (`Add snapshot publish command`) scoped to one logical change.
- Reference issues with `[#123]` or GitHub keywords when applicable.
- Pull requests must include: change summary, testing notes, screenshots for UI updates, and mention of new/updated docs or tests.
- Keep PRs focused and call out follow-up work in the worklog (`docs/codex/WORKLOG.md`) when deferring tasks.

## Session Workflow & Documentation
- Before coding, review `docs/codex/WORKLOG.md` and open the next session entry; align TODOs with the repository state.
- Scan pending documentation changes (`docs/dev/**`, `docs/user/**`, `docs/codex/notes/OUTLINE.md`) and update immediately or log follow-ups.
- After each feature/refactor, cross-check code vs. docs; update docs on the spot or record the discrepancy with owner and next steps.
- When closing a session, read relevant Markdown files end-to-end to catch drift, document outcomes in the worklog, and note pending actions.
- Prefer non-throwing flows in production code: handle errors, log context-rich messages, and surface recoverable issues gracefully.

## Compatibility & Refactoring Policy
- Until the first public major release (1.0.0), backwards compatibility is not required. Favour clean refactors over legacy shims and remove obsolete code; update callers and documentation immediately when behaviour changes.

## Security & Configuration Tips
- Never commit secrets; store environment values in `.env.local` or Symfony’s secrets vault. Releases should rely on installer-generated `APP_SECRET`.
- Validate container wiring via `php bin/console lint:container` after introducing or refactoring services.
- Review security rules in `config/packages/security.yaml` for each feature and ensure role/ACL updates are documented.

## References
- Developer manual: `docs/dev/MANUAL.md`
- Worklog & session notes: `docs/codex/WORKLOG.md`
- Concept outline: `docs/codex/notes/OUTLINE.md`
- Environment recap: `docs/codex/ENVIRONMENT.md`
- README entry point: `README.md`
- Helper scripts & snippets: store under `.codex/`
