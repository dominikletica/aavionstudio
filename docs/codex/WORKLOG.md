# Developer Notes

> Maintainer: Codex (GPT-5)  
> Purpose: Track implementation decisions, open questions, and follow-up tasks during development.

## TODO
### Core Platform (P0 | XL)
#### Hosting & Installer
- [x] Finalise rewrite-first vs root fallback handling, including installer warnings and documentation hooks
- [x] Build installer wizard steps (diagnostics → environment → storage/db → admin account → summary) with `.env.local.php` generator
- [ ] Implement health checks for PHP extensions, writable `var/*` directories, and SQLite availability with actionable remediation hints
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
- [ ] Review `bin/release` workflow so new core-platform steps (manifest cache, installer assets) remain compatible with the existing prebuild process; adjust only if gaps emerge

### Feat: User Management & Access Control (P0 | L)
- [ ] Create user entity, login flow, and password reset process
- [ ] Seed role hierarchy + capability registry sourced from module manifests
- [ ] Build admin UI for user/role/API key management with audit logging
- [ ] Cover authentication, role assignment, and API-key flows with functional/security tests

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

## Roadmap To Next Release
- [x] **Step 1:** Discuss open questions & confirm hosting/security decisions
- [ ] **Step 2:** Implement Core Platform & architecture foundation
- [ ] **Step 3:** Implement User Management & Access Control
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
