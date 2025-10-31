# Developer Notes

> Maintainer: Codex (GPT-5)  
> Purpose: Track implementation decisions, open questions, and follow-up tasks during development.

## TODO
### Core Platform (P0 | XL)
#### Hosting & Installer
- [x] Finalise rewrite-first vs root fallback handling, including installer warnings and documentation hooks
- [x] Build installer wizard steps (diagnostics → environment → storage/db → admin account → summary) with `.env.local.php` generator
- [x] Implement health checks for PHP extensions, writable `var/*` directories, and SQLite availability with actionable remediation hints
- [x] Deliver root-level `index.php` compatibility loader plus hardening (`Options -Indexes`, deny sensitive paths) and banner logic

#### Module System
- [x] Implement module manifest contract & registry (services, routes, navigation, theme slots, scheduler hooks)
- [x] Persist module metadata in `system.brain` for future enable/disable flows; load manifests during cache warmup with validation errors surfaced (installer does not manage modules yet)
- [x] Integrate module-provided capabilities into the central registry for later features (user access, admin navigation)

#### Database & Migrations
- [x] Prepare initial Doctrine migrations for core tables (`app_project`, `app_entity`, `app_entity_version`, `app_draft`, `app_schema`, `app_template`, `app_relation`, `app_user`, `app_api_key`, `app_log`)
- [x] Configure dual SQLite connections with attach listener, busy timeout, and connection health checks
- [x] Seed baseline configuration records (system settings, installer state) via a hybrid approach (lightweight fixtures/config files plus installer overrides) to keep defaults easy to evolve

#### Testing & Tooling
- [x] Create unit/integration test harness covering installer flow, module loader bootstrap, and database attachment
- [x] Add smoke tests for root loader rewrite detection and installer diagnostics endpoints
- [x] Review `bin/release` workflow so new core-platform steps (manifest cache, installer assets) remain compatible with the existing prebuild process; adjust only if gaps emerge

### Feat: User Management & Access Control (P0 | L)
#### Authentication & Core Entities
- [x] Add migration(s) to extend `app_user` (rename `password` → `password_hash`, add status/last login) and create supporting tables (`app_role`, `app_user_role`, `app_project_user`, `app_password_reset_token`, `app_remember_me_token`, `app_audit_log`, `app_user_invitation`).
- [x] Implement DB-backed user provider, user model, password hasher config, login/logout/remember-me controllers, and rate limiting for authentication attempts.
- [x] Wire password reset flow (token storage, email delivery, controllers) and record audit events for credential changes.
- [x] Provide invitation backend (tokens, persistence, audit logging) for administrators.
- [x] Implement invitation management UI and activation onboarding for administrators.

#### Roles, Capabilities & Project Memberships
- [x] Materialise global role hierarchy + default seeds; expose capability registry service that hydrates from module manifests and persists defaults in the database.
- [x] Implement project membership repository (`app_project_user`) abstraction to supply voters/admin UI.
- [x] Build project membership voters that combine global roles, project overrides, and capability requirements.

#### Admin UI & API Keys
- [x] Add invitation management screen (listing/create/cancel) under `/admin/users/invitations` with email delivery and audit logging.
- [x] Build `/admin/users` management interface (listing, filters, detail view) with forms for profile edits, role assignments, project overrides, invitations, and password resets. *(Project overrides complete; password reset trigger still pending.)*
- [x] Implement API key issuance/revocation (UI + CLI) with scoped capability enforcement and hashed storage. *(Scopes + expiry captured; capability enforcement middleware still pending once API surface lands.)*
- [x] Surface audit log viewer for security events (auth attempts, role changes, API key updates) with filters.

#### Testing & Tooling
- [x] Add unit/functional tests covering voter decisions and API key HTTP endpoints (login + admin flows + voter probe + API key REST endpoints are now covered; remaining HTTP API auth enforcement will land with the write API).
- [x] Provide documentation updates (developer + user manuals) for login, roles, project membership, API keys, and troubleshooting; schedule follow-up smoke tests in release workflow. *(Docs updated alongside recent features; smoke-test automation still a later task.)*

### Feat: Admin Studio UI (P0 | L)
- [ ] Scaffold layout (sidebar, header, notifications) with Tailwind components
- [ ] Wire navigation builder consuming module manifests + capability checks
- [ ] Implement search palette and contextual help drawer
- [ ] Add Cypress/Stimulus integration tests (or Symfony Panther) for core navigation UX

### Feat: Schema & Template System (P0 | L)
- [ ] Implement schema/template persistence with versioning
- [ ] Integrate JSON schema validation into draft workflow
- [ ] Build admin schema builder + template preview tools
- [ ] Write unit tests for schema validation + template resolution helpers

### Feat: Draft & Commit Workflow (P0 | L)
- [ ] Build DraftManager service with autosave + optimistic locking
- [ ] Implement commit transaction promoting drafts to active versions with event dispatch
- [ ] Integrate editor UI (CodeMirror + schema validation) with status indicators
- [ ] Add feature tests covering draft autosave, conflict handling, and commit lifecycle

### Feat: Snapshot & API Delivery (P0 | L)
- [ ] Implement SnapshotManager with atomic writer and metadata tracking
- [ ] Expose read-only API endpoints with caching headers and rate limiting
- [ ] Add CLI commands for snapshot rebuild and pruning
- [ ] Provide integration tests validating snapshot generation and API responses

### Feat: Frontend Delivery & Rendering (P0 | L)
- [ ] Implement catch-all frontend controller backed by snapshots and schema templates
- [ ] Build base Twig layouts with error page handling and navigation integration
- [ ] Add preview mode for drafts with access controls
- [ ] Ensure error-page entities in `default` project fall back to seeded Twig templates (render tests)
- [ ] Write functional tests for routing, locale handling, and preview safeguards

### Feat: Resolver Pipeline (P1 | L)
- [ ] Implement shortcode tokenizer and schema field annotations
- [ ] Build reference and query resolver services with cycle detection
- [ ] Surface resolver warnings/errors in commit UI and logs
- [ ] Add unit tests covering resolver operators, cycle detection, and error codes

### Feat: Caching & Performance Strategy (P1 | M)
- [ ] Configure cache namespaces/adapters and default TTLs
- [ ] Hook cache invalidation into snapshot and draft events
- [ ] Expose cache stats/controls via maintenance module
- [ ] Benchmark cache hit/miss scenarios; add tests for invalidation hooks

### Feat: Write API & Integration Surface (P1 | M)
- [ ] Define OpenAPI spec for write endpoints and response contracts
- [ ] Implement API key scope checks and HMAC signing support
- [ ] Build webhook dispatcher with retry queue
- [ ] Create API integration tests (success + failure paths, rate limits, signatures)

### Feat: Media Storage & Delivery (P0 | L)
- [ ] Implement media storage abstraction, metadata persistence, and upload workflows
- [ ] Build protected delivery controller with signed URLs and ACL checks
- [ ] Integrate media fields into schema/editor and exporter pipelines
- [ ] Add cleanup command for orphaned assets and retention policies
- [ ] Cover upload, metadata, and download flows with integration tests

### Module: Relation Manager (P1 | M)
- [ ] Implement hierarchy service with materialized path operations
- [ ] Build drag-and-drop tree UI for entity arrangement
- [ ] Emit hierarchy change events for downstream modules
- [ ] Write tests for hierarchy integrity, move operations, and UI endpoints

### Module: Navigation Builder (P1 | M)
- [ ] Design menu/menu-item persistence and draft workflow
- [ ] Create admin tree editor with visibility rules
- [ ] Provide frontend renderer + sitemap generator
- [ ] Test menu rendering, sitemap output, and visibility rules

### Module: Frontend Theming & Site Delivery (P1 | L)
- [ ] Implement theme manifest loader and activation flow
- [ ] Build theme management UI with preview functionality
- [ ] Integrate Tailwind build pipeline for multiple themes
- [ ] Add tests ensuring theme overrides render correctly and fallbacks apply

### Module: Exporter (P1 | M)
- [ ] Implement export presets + JSON/JSONL writers
- [ ] Build admin UI for presets and run history
- [ ] Add optional TOON converter behind feature flag
- [ ] Provide tests verifying export content, presets, and TOON feature flag behaviour

### Module: Admin Maintenance Console (P1 | M)
- [ ] Create dashboard cards for cache, snapshots, queue, migrations
- [ ] Wrap CLI operations in services for UI-triggered actions
- [ ] Add health check aggregations (permissions, disk usage, extensions)
- [ ] Add functional tests for maintenance actions and permission boundaries

### Module: Backup & Restore (P1 | L)
- [ ] Implement backup command + archive manifest with checksums
- [ ] Build admin UI for scheduling, download, restore
- [ ] Add retention policy and optional remote storage hooks
- [ ] Test backup archives, restore flows, and scheduling/retention logic

### Module: Diff View (P2 | M)
- [ ] Develop diff engine for JSON/Markdown comparison
- [ ] Integrate diff viewer into admin UI and commit modal
- [ ] Cache diff results for common comparisons
- [ ] Cover diff edge cases (large payloads, markdown comparison) with tests

### Follow-up Tasks (Visit periodically)
- [ ] Wire API key-based authentication/authorization into the public HTTP API once the write/read endpoints land (reuse `ApiKeyManager` issuance data).
- [ ] Add smoke checks for `/admin/api/api-keys` in the release workflow to ensure serialization changes remain backwards compatible.

## Roadmap To Next Release
Vision: Create a fully functional prototype (MVP+) as 0.1.0 dev-release:
- [x] **Step 1:** Discuss open questions & confirm hosting/security decisions
- [x] **Step 2:** Implement Core Platform & architecture foundation
- [x] **Step 3:** Implement User Management & Access Control
- [ ] **Step 4:** Build Admin Studio UI shell & navigation
- [ ] **Step 5:** Deliver Schema/Template system & Draft/Commit workflow
- [ ] **Step 6:** Implement Snapshot delivery, Frontend rendering, and Resolver pipeline
- [ ] **Step 7:** Add Media storage, Caching strategy, and Write API integration
- [ ] **Step 8:** Ship priority modules (Relation, Navigation, Theming, Exporter, Maintenance, Backup)
- [ ] **Step 9:** Polish stretch module (Diff View) & regression test suite

## Planned Implementations, Outlined Ideas
> Concept drafts live in `docs/codex/notes/*.md`

- [Project Outline](./notes/OUTLINE.md)
- [Feat: Core Platform](./notes/feat-core-platform.md)
- [Feat: Draft & Commit Workflow](./notes/feat-draft-commit.md)
- [Feat: Resolver Pipeline](./notes/feat-resolver-pipeline.md)
- [Feat: Snapshot & API Delivery](./notes/feat-snapshot-api.md)
- [Feat: Frontend Delivery & Rendering](./notes/feat-frontend-delivery.md)
- [Feat: User Management & Access Control](./notes/feat-user-access.md)
- [Feat: Admin Studio UI](./notes/feat-admin-studio.md)
- [Feat: Schema & Template System](./notes/feat-schema-templates.md)
- [Feat: Media Storage & Delivery](./notes/feat-media-storage.md)
- [Feat: Write API & Integration Surface](./notes/feat-api-write.md)
- [Feat: Caching & Performance Strategy](./notes/feat-caching-strategy.md)
- [Module: Exporter](./notes/module-exporter.md)
- [Module: Admin Maintenance Console](./notes/module-admin-maintenance.md)
- [Module: Relation Manager](./notes/module-relation-manager.md)
- [Module: Navigation Builder](./notes/module-navigation.md)
- [Module: Frontend Theming & Site Delivery](./notes/module-frontend-theming.md)
- [Module: Backup & Restore](./notes/module-backup-restore.md)
- [Module: Diff View](./notes/module-diff-view.md)

## Session Logs
### 2025-10-29
- Initialised Symfony skeleton in repository and verified clean install
- Added Tailwind Bundle, importmap assets, CodeMirror integration, and Stimulus controller scaffold
- Extended composer requirements (Flysystem, UID, JSON schema validator, GeoIP, Rate Limiter, Messenger, PHP extensions)
- Configured Doctrine for dual SQLite databases with attach listener, + ULID/UUID types
- Updated `.env` defaults for SQLite, configured messenger/lock cache, confirmed runtime via `php bin/console about`
- Created contributor guide (`AGENTS.md`) and aligned docs under `docs/codex/**`
- Translated and updated project outline to English with deployment strategy revisions

### 2025-10-29 (Session 2)
- Kicked off feature-outline drafting for modular architecture and implementation planning
- Added detailed drafts for core platform, workflow, resolver, snapshot/API, and initial modules
- Established documentation style guide, templates, manuals, and tooling for parallel doc updates
- Expanded draft library covering user management, admin studio, schema/templates, write API, caching, theming, navigation, backup, and diff tooling
- Reorganised TODOs by feature/module with granular tasks for implementation planning

### 2025-10-30
- Added release automation (`bin/release`) creating environment/version/channel-specific packages with metadata
- Clarified documentation portals, manuals, and release workflow references; added user/dev section skeletons and class map
- Generated dummy `release.json` for tooling; release script now cleans Tailwind binaries and rewrites metadata ahead of staging

### 2025-10-30 (Session 2)
- Adjusted release script to generate metadata inside the build directory and atomically refresh the tracked `release.json`, plus aligned the release workflow guide
- Revamped init-script to initialize the repository and configure environment (install dependencies, run neccessary commands like compiling assets and setup .env.local to get started) 

### 2025-10-30 (Session 3)
- Reviewed roadmap step for hosting/security decisions; confirmed rewrite-first deployment with root loader flagged as compatibility fallback only
- Locked in requirement that all operational flows (theme management, backups, installs) remain browser-driven so end users never need CLI access
- Captured answers to open questions across core outlines (caching, schemas, access control, frontend delivery, themes, media, backups, API) to unblock implementation backlog
- Expanded feature/module outlines with detailed data models, UI flows, and pseudocode to make upcoming implementation phases deterministic

### 2025-10-30 (Session 4)
- Kicked off Core Platform implementation (Step 2) focusing on database foundations and shared defaults
- Added SQLite busy-timeout/foreign-key pragmas, attachment health checks, and PHPUnit coverage for the connection listener
- Seeded initial Doctrine migration with core tables (system vs user DB split) plus config-driven defaults (`config/app/system_settings.php`, `config/app/projects.php`)
- Introduced filesystem-based module discovery/registry with repository metadata for future `.aavmodule` update scans, updating docs and tests accordingly
- Synced module metadata with `app_module_state`, exposed aggregated capabilities/parameters, and scaffolded the browser setup wizard (`/setup`) with diagnostics-friendly Twig template and functional test
- Added root compatibility loader with rewrite diagnostics and installer warnings, shipped Apache/IIS fallback configs, nginx guidance, and corresponding unit/functional tests plus documentation updates
- Expanded installer diagnostics with actionable extension and filesystem checks (hints for `var/*` and `public/assets/`), keeping tests/docs in sync
- Reviewed release packaging and init tooling so new hardening assets ship cleanly (Tailwind cache removal, asset rebuild cleanup) and updated documentation accordingly
- Routed SQLite busy-timeout env default through a container parameter so `bin/init` and release builds run without EnvVarProcessor fallback errors
- Documented the implemented foundation in `docs/dev/sections/architecture/core-platform.md` for future contributors
- Code-Review: Updated ModuleStateSynchronizer so new module rows honour the manifest’s default enabled flag unless the module is locked.

### 2025-10-31
- Kick-off: Roadmap Step 3 (User Management & Access Control) – audited feature outline, captured schema/auth updates, and expanded TODOs into implementation phases covering migrations, security wiring, admin UI, API keys, and testing.
- Added initial user/access schema migration scaffolding (roles, project memberships, credential tokens, audit log) ready for implementation
- Implemented core authentication stack (DB user provider, status checker, login/logout with remember-me, rate limiting) with Twig login template and unit coverage (`tests/Security/AppUserProviderTest.php`), refreshed architecture docs.
- Added capability registry + synchronizer to seed `app_role_capability` from module manifests with audit trail, including unit coverage (`tests/Security/CapabilitySynchronizerTest.php`) and role hierarchy wiring.
- Implemented password reset token manager with hashed selector/verifier storage, purge helper, and unit tests (`tests/Security/Password/PasswordResetTokenManagerTest.php`).
- Wired password reset request/reset controllers, forms, email template, audit logging, and functional coverage (`tests/Controller/Security/PasswordResetControllerTest.php`); updated security layout/templates.
- Built invitation infrastructure (DB schema, `UserInvitationManager`, audit logging, unit tests) to support admin-triggered onboarding flows.
- Added admin invitation management screen (listing, create, cancel) with Twig UI, mail delivery, and functional coverage (`tests/Controller/Admin/UserInvitationControllerTest.php`).
- Implemented project membership repository abstraction (`ProjectMembershipRepository`) with unit coverage, laying groundwork for project-scoped voters.
- Added project capability voter (`ProjectCapabilityVoter`) resolving global roles + project overrides to grant capabilities, with unit coverage.
- Completed invitation onboarding flow: invitees set profile/password via `/invite/{token}`, accounts are created and invitations marked accepted with coverage.
- Extended invitation acceptance test to verify password hashing, role persistence, and a full login with the invited account; documented onboarding flow for developers and administrators.
- Shipped `/admin/users` management UI with listing/search filters, profile + role editor, audit logging, and functional coverage; documented tooling for developers and administrators.
- Added API key manager service, CLI issuance command, and admin UI for creation/revocation with audit logging and tests; documented developer + user flows.
- Enhanced `/admin/users/{id}` to manage per-project overrides (role + extra capabilities) with functional coverage and documentation updates.
- Delivered security audit log viewer (`/admin/security/audit`) with filters, repository helper, Twig view, and functional coverage; updated developer/user documentation and roadmap tracking.
- Added admin-triggered password reset flow with email delivery, audit logging, and functional coverage from the user detail screen.
- Updated project capability voter to support structured capability lists from the new project override UI and extended unit coverage for legacy + new permission formats.
- Added functional coverage for login success/failure to ensure authentication flow and error handling remain deterministic.
- Added project capability probe endpoint + functional tests to exercise voter decisions within HTTP requests.
- Exposed admin REST endpoints for API key listing/creation/revocation with functional coverage ensuring JSON contracts and audit logging.
