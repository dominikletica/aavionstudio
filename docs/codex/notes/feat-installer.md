# Feat: Installer & Setup Wizard (P0 | Scope: XL)

**Status:** Draft – aligned with maintainer feedback (2025-11-04).  
**Purpose:** Finalise the browser-driven setup so instances can be provisioned end-to-end without shell access while reusing the existing `bin/init` pipeline.

## Goals & success criteria
- Operators complete `/setup` in under five minutes; every step works on shared hosting without manual CLI.
- Finalisation triggers `bin/init` (potentially with a future `--setup` flag) to materialise `.env.local`, install dependencies, rebuild assets, and apply migrations; installer preferences persist in the database via `SetupConfigurator`.
- System settings (instance name, locale, feature toggles) and the default project metadata are stored centrally; the default project slug stays fixed to `default`.
- Wizard enforces mandatory diagnostics before progression, keeps optional warnings visible until acknowledged, and streams progress through the existing action overlay (logs visible in the UI and saved under `var/log/setup/`).
- No invitation flow during setup: the first administrator defines credentials immediately and the installer locks itself afterwards.
- Documentation (developer + user manuals) and the class map reflect new services; internal outlines remain internal-only.

## Current state snapshot
- **Delivered:** Diagnostics dashboard (rewrite, extensions, filesystem, SQLite), setup redirect subscriber, streamed action endpoint supporting `log/init/lock` steps, lockfile handling, Tailwind-enabled installer layouts, session-scoped action tokens.
- **Recently added:** `SetupConfiguration` and `SetupConfigurator` scaffolding, enriched default system settings/projects, runtime `SystemSettings` cache, admin profile field registry, expanded Twig templates.
- **Remaining gaps:** Environment/storage/admin steps still static; no forms or persistence wiring. `SetupConfigurator` does not yet consume session data. Action pipeline always shells out to `bin/init`, which is acceptable but needs better integration (secret hand-off, configuration persistence, logging).

## Guiding principles
- **Browser-first:** Everything must run from the wizard; CLI remains optional for developers.
- **Reuse `bin/init`:** The installer orchestrates `bin/init` rather than duplicating logic. Optimisations happen inside the script (e.g. future `--setup` flag) so both installer and CLI entrypoint stay in sync.
- **Deterministic defaults:** Default project slug and routing stay stable. Global settings control locale/timezone; projects inherit these values.
- **Single storage root:** User data (databases, uploads, snapshots, backups) lives under `var/storage` by default; the installer can relocate the root but not diverge per project.
- **Lock discipline:** The existing lockfile mechanism is retained; the session token is sufficient protection once the lock exists.
- **Operational breadcrumbs:** Stream logs to the overlay and persist them at `var/log/setup/<timestamp>.ndjson`; operators can copy content manually if needed.

## UX flow & data capture

### Step 1 – Diagnostics
- Keep current cards for rewrite, extensions, filesystem, and SQLite health.
- Add “Re-run checks” action calling `POST /setup/diagnostics` and refreshing the data via fetch.
- Block navigation until mandatory requirements pass; hide/disable the step navigation when requirements fail.
- Persist acknowledgement for non-blocking warnings (e.g. rewrite fallback banner) in `SetupConfiguration::rememberDiagnosticsAcknowledgement()`.

### Step 2 – Environment configuration
- Build `InstallerEnvironmentType` capturing: instance name, tagline, support email, base URL, locale, timezone, feature toggles (user registration, maintenance mode), cache defaults, optional mail transport, trusted proxies, cookie domain.
- Provide a “Generate secret” control that produces a 32-byte random value; store it in session and send it to `bin/init` as an argument. If omitted, `bin/init` falls back to its internal secret generator.
- Show a live summary (key/value) of selections instead of writing an environment file preview.
- Persist submitted data via `SetupConfiguration::rememberSystemSettings()` and a new `rememberEnvironmentOverrides()` helper.
- Follow Tailwind utility classes already shipped with the project; extend design tokens where needed so future rebuilds remain automatic.
- Defer file writes until finalisation; the environment writer validates the complete payload before touching `.env.local`.

### Step 3 – Storage & database
- Scope: confirm or adjust the storage root (`var/storage` default) that also includes `system.brain` and `user.brain`.
- Limit database driver to SQLite for the initial release; surface other drivers as read-only roadmap notes.
- Persist the chosen storage root via `SetupConfiguration::rememberStorageRoot()`; compute child directories (`var/storage/databases`, `var/storage/uploads`, …) automatically.
- Surface filesystem write checks for the proposed root before allowing progression.

### Step 4 – Administrator setup
- Form collects email, display name, password + confirmation (with strength meter against `core.users.password_policy`), locale, timezone, optional recovery contact, and MFA requirement toggle (stored for later enforcement).
- Validate password strength client-side and server-side; block submission until policy passes.
- Persist data through `SetupConfiguration::rememberAdminAccount()`. Store the plaintext only in session; the hash is generated when finalising via `UserCreator` after `bin/init` persisted the APP_SECRET.
- Do not offer invitation flows. Immediately after finalisation, redirect to `/admin` (which already forwards unauthenticated users to `/login`) and instruct the operator to sign in with the newly created credentials.

### Step 5 – Summary & finalisation
- Render consolidated summaries for diagnostics, environment overrides, storage root, and admin details (excluding password). Highlight unresolved warnings.
- Offer copy-to-clipboard buttons for the secret and for the chosen storage root.
- Keep the action overlay button (“Create databases & finish setup”) but update its step list to reflect the refined pipeline (see below).
- Note in the summary that the wizard writes verified overrides into `.env.local` immediately before `bin/init` runs, preserving manual `bin/init` usability thanks to fallback defaults.

### In-app help content
- Replace direct links to Markdown docs with a JSON-based help catalogue (for example `help.json` (for localization: `help.de.json`). Define a simple schema `{ "section": "setup", "type": "inline_help|tooltip|manual_page|...", "title": "...", "body": "..." }` that the UI can lazy-load for tooltips and sidebars.
- Keep the JSON stub deterministic so localisation or doc syncing can be automated later.

## Backend & persistence plan
- Extend `SetupConfiguration` with explicit methods:
  - `rememberDiagnosticsAcknowledgement()`
  - `rememberEnvironmentOverrides()`
  - `rememberStorageRoot()`
  - `rememberAdminAccount()`
  - `clear()` (already present) to wipe the session after lock.
- Introduce `SetupEnvironmentWriter` that:
  - Validates required overrides (APP_ENV, APP_DEBUG, DATABASE_URL, storage paths, mailer DSN, trusted proxies, cookie domain).
  - Reads the current `.env.local` when present, merges new keys while preserving unknown values, and writes atomically using a temp file + rename.
  - Falls back to defaults from `.env` when a field is missing so direct `bin/init` executions continue to work outside the wizard.
- `SetupConfigurator::apply()` consumes the aggregated session data to persist:
  - System settings (instance name, locale, timezone, feature toggles, cache defaults, support email).
  - Default project display name/description while keeping slug `default`; projects inherit the global locale/timezone.
  - Profile field configuration and other defaults that already exist in `config/app/system_settings.php`.
- Reload `SystemSettings` after persistence so runtime consumers pick up the new values without cache flush.
- Prepare a JSON payload (e.g. `var/setup/runtime.json`) that captures validated overrides and the admin seed data. The installer deletes or scrubs this file after `bin/init` completes.

### Hand-off to `bin/init`
- Extend `bin/init` with two optional flags:
  - `--setup` to enable installer-specific behaviour.
  - `--payload=/absolute/path/to/runtime.json` to locate the shared hand-off file.
- When `--payload` is provided:
  1. Run the existing workflow (write `.env.local`, Composer install, Tailwind build, console commands) using the already merged `.env.local`.
  2. After the main pipeline completes successfully, call a new console command (`php bin/console app:setup:seed --payload=...`) that:
     - Reads the JSON payload, hashes the admin password via `UserCreator`, persists the admin, and records audit events.
     - Applies any residual system settings to ensure parity with `SetupConfigurator`.
     - Wipes the plaintext password from disk (delete file or overwrite with a redacted structure).
  3. Continue to print status lines so the action overlay surfaces progress (“Seeding admin account… done”).
- If `--payload` is absent (developers running `bin/init` manually or Codex Cloud bootstrap), the script behaves exactly as before and skips the seeding command.
- All new behaviour must degrade gracefully when the payload file is missing, malformed, or incomplete—`bin/init` should warn and exit with a failure before making destructive changes.

## Action execution pipeline
- Update the action step list emitted by `InstallerController` to:
  1. `validate` – Re-run diagnostics server-side and ensure session data is complete.
  2. `write_env` – Use `SetupEnvironmentWriter` to merge overrides into `.env.local` and persist the JSON payload for `bin/init`.
  3. `configure` – Call `SetupConfigurator->apply()` to persist system settings and project metadata (idempotent if rerun).
  4. `init` – Invoke `bin/init` with `--setup`, the selected environment, optional `--secret=<value>`, and `--payload=var/setup/runtime.json`. The script remains the single source of truth for dependency install, asset rebuild, migrations, and cache warmup.
  5. `lock` – Touch the lockfile via `SetupState->markCompleted()`, delete the payload file, and clear session data (including plaintext password).
- `ActionExecutor` continues to stream log lines through the existing NDJSON mechanism. Extend it to pipe each message into a log file under `var/log/setup/<timestamp>.ndjson`.
- If the operator restarts the wizard mid-run, the lock prevents duplication; the UI should surface a friendly “Already installed” message.

## Controller & routing updates
- Add POST handlers for each step (`/setup/environment`, `/setup/storage`, `/setup/admin`) that validate forms, persist via `SetupConfiguration`, and return JSON status for Turbo/Stimulus-driven form submission.
- Implement `POST /setup/diagnostics` returning the same payload used on initial render.
- Add a lightweight `SetupSummaryBuilder` service that aggregates all session data. Use it in both the summary template and any JSON preview endpoint required by Stimulus controllers.
- Keep routing constrained within the installer namespace; non-setup routes remain guarded by `SetupRedirectSubscriber`.

## Security, error handling & observability
- Continue relying on the session-scoped setup token issued by `SetupAccessToken`; rotate it only after successful finalisation (handled by clearing the session).
- Sanitize all user input before writing to settings or passing to the shell (e.g. whitelist characters for storage paths and base URL).
- Validate admin email uniqueness before creating the user; surface actionable validation feedback in the form.
- Log security-relevant events (`admin.seeded`, `setup.completed`) via `SecurityAuditLogger` including client IP and user agent.
- Throttle the finalisation endpoint at the HTTP layer to guard against repeated triggering.

## Testing strategy
- **Unit:** Cover `SetupConfiguration` merging logic, `SetupConfigurator` persistence (with SQLite test DB), and any helpers introduced for diagnostics re-runs or summary building.
- **Integration:** End-to-end test hitting `/setup` flow with simulated form submissions, asserting that `SetupEnvironmentWriter` updates `.env.local`, `bin/init --setup --payload` is invoked (mocked), and that settings/admin records are created.
- **Functional:** Browser-oriented tests ensuring navigation gating, acknowledgement workflow, form validation messages, and lock redirects.
- **Regression:** Ensure multiple calls to finalisation short-circuit gracefully, logs write to `var/log/setup`, and session data clears after success.
- Tag installer-specific suites so they can be executed via `php bin/phpunit --group installer`.

## Implementation phases
1. **Session forms & controllers:** Build forms, POST endpoints, session persistence, diagnostics refresh, and summary builder.
2. **Configurator & env writer:** Extend `SetupConfigurator`, introduce `SetupEnvironmentWriter`, ensure SystemSettings reload, solidify storage root handling, and update Twig templates to consume summary data.
3. **Action executor & `bin/init`:** Adjust step list, implement JSON payload hand-off, extend `bin/init` (`--setup`, `--payload`, post-run seeding command), and ensure setup logs write to disk.
4. **Admin seeding & polish:** Finalise the console seeding command, redact/delete plaintext passwords after hashing, add audit logging, in-app help JSON, accessibility tweaks, and translation updates.

## Documentation & follow-ups
- Once functionality lands, update:
  - `docs/dev/MANUAL.md` installer section (describe workflow, secret handling, storage root).
  - `docs/user/MANUAL.md` quick installation guide (step-by-step for operators).
  - `docs/dev/classmap.md` for new services (`SetupSummaryBuilder`, updated configurator).
- Log session outcomes in `docs/codex/WORKLOG.md`. Keep internal outlines (`docs/codex/notes/**`) unlinked from public manuals.
- Capture any deferred roadmap items (e.g. non-SQLite support, optional invitation emails) in the worklog TODO section.

## Resolved decisions & remaining questions
- **Database drivers:** Ship with SQLite-only UI; other drivers stay out of scope until we solve dual-database parity and testing.
- **Storage layout:** Consolidate under one configurable root (`var/storage`) instead of per-project overrides.
- **Admin onboarding:** Password is chosen during setup; follow-up emails remain optional niceties, not part of MVP.
- **Logs:** Keep overlay output and file persistence; no separate download feature.
- **Future flags:** If we ever slim down `bin/init` for installer use, introduce a dedicated flag while keeping the script the canonical entrypoint.
