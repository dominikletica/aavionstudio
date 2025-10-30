# aavion Studio – Concept Outline (Draft 0.1.0-dev)

> **Repository:** dominikletica/aavionstudio  
> **Status:** Draft – updated 29 Oct 2025  
> **Audience:** Development & Technical Leadership  
> **Focus:** Shared-hosting friendly, minimal setup, LLM-ready exports  
> **Authors:** Dominik Letica & project collaborators  
> **Contact:** dominik@aavion.media

---

## 1. Executive Summary

aavion Studio is a lightweight full-stack CMS built on Symfony that emphasises:
- Schema-driven content models with JSON payload validation per entity
- Draft → commit workflows with Git-like versioning
- Resolver support for `[ref]` / `[query]` shortcodes (cross-links & filtered lookups)
- Deterministic published snapshots per project as the single source of truth for the frontend, API, and exporters
- Optional LLM-oriented exports (JSON, JSONL, optional TOON flavour)
- Optional inline/onsite context delivery for AI-assisted browsing tools

💬 **Note:** Drafts and version history stay in SQLite. Only resolved snapshots are distributed to consumers, ensuring deterministic reads everywhere.

---

## 2. Non‑Functional Goals

- **Hosting:** PHP 8.2+ (tested up to 8.4), Apache + `.htaccess`, shared-hosting compatible; CLI optional for developers only.
- **Portability:** Deliverable as a single folder; browser-based installer handles environment checks, database provisioning, and initial admin creation.
- **Performance & Resilience:** Atomic snapshot writes, defensive caching layers, Symfony Lock to prevent concurrent publish conflicts.
- **Security & Privacy:** Role-based access, API keys, rate limiting, IP anonymisation after 14 days, optional MaxMind enrichment.
- **Localisation:** Full UI i18n coverage; API responses remain English by default; ship with German + English, provide translation guide for contributors.

---

## 3. Tech Stack & Alternatives

- **Primary Framework:** Symfony 7.3 (Framework, Security, Validator, Serializer, Translation, Monolog, Messenger, Rate Limiter, UID).
- **Frontend:** Twig + Tailwind (via Symfonycasts Tailwind Bundle with AssetMapper), Stimulus + Turbo for interactions, optional Alpine.js where useful, CodeMirror editor integrated via Importmap.
- **Database:** SQLite: `system.brain` (configs, logs, system data) + `user.brain` (content) attached at runtime. JSON payloads stored as TEXT, validated in PHP.
- **API:** Read-only on published snapshots; optional write API with instant commits.

💬 **Fallback:** Default guidance is to configure the web server (Apache `mod_rewrite`, nginx rules, IIS web.config) to point the document root at `public/`. A compatibility layer using a root-level `index.php` (delegating to `public/index.php`) will be provided for environments without rewrite support but must be treated as a last resort.

---

## 4. Distribution & Setup

**Preferred Setup – Webroot → `public/`**
- Configure virtual hosts (Apache/nginx/IIS) so the document root targets `public/`.
- Use shipped rewrite rules (`public/.htaccess`, nginx snippet, IIS `web.config`) to route all requests through `public/index.php`.
- Releases contain prebuilt assets and `vendor/`; the webroot stays clean and sensitive files remain unreachable.

**Fallback Setup – Root Front Controller**
- Provide a minimal `index.php` in the project root that bootstraps `public/index.php` for hosts without rewrite or custom docroot support.
- Support an explicit `?route=` parameter to emulate routing without rewrites.
- ⚠️ **Security notice:** Without rewrites or dedicated docroot isolation, upstream protection of config/vendor files cannot be guaranteed. Treat this as a temporary compatibility mode and document the risk clearly to operators.

**Installer**
- Browser-based workflow at `/setup`: environment diagnostics, permission checks, SQLite database creation, admin seeding, `.env.local.php` generation.
- Releases remain drop-in ZIPs (with `vendor/` + prebuilt `public/assets/`); end users never invoke Composer or Node. Developers run `php bin/console tailwind:build` locally before packaging.

---

## 5. Domain Model (Simplified Entities)

- **Project:** slug, title, settings (theme, gateway config, error page bindings)
- **Entity:** project_id, slug, type, subtype, parent_id, flags (visible, menu, exportable, locked), meta
- **EntityVersion:** entity_id, version_id (ULID), payload_json, author_id, committed_at, commit_message, active_flag
- **Draft:** entity_id, payload_json, updated_by, updated_at, autosave flag
- **Schema:** name, scope (global/project), json_schema, template_ref, config
- **Template:** name, Twig source, metadata
- **ApiKey:** user_id, key_hash, label, scopes, created_at, last_used_at
- **User:** profile, roles, locale, authentication metadata
- **Log:** type (ERROR/WARNING/DEBUG/AUTH/ACCESS), ctx_json, created_at

IDs use ULIDs or UUIDs through `symfony/uid`. Soft delete occurs via flags/versioning; hard delete only through ACL-protected modules. Relations are represented using dedicated relation entities to support bidirectional and typed links.

---

## 6. Hierarchy & URLs

- **Model:** Materialized path with stored path + depth columns to enable efficient tree reads and batch moves.
- **Routing:** `/<project>/<path-of-slugs>`; the default project `default` renders at root without the project prefix.
- **Constraints:** Parents with active children cannot be deleted or deactivated (enforced by DB constraints + domain checks).

💬 **Rationale:** Materialized path hits the sweet spot between simplicity and performance. The UI can display adjacency lists while persistence maintains deterministic paths.

---

## 7. Data Storage & JSON Strategy

- **SQLite Attachment:** `system.brain` attaches `user.brain` automatically using a Doctrine post-connect listener.
- **JSON Payloads:** Stored as TEXT; validated using JSON Schema libraries (e.g., `justinrainbow/json-schema`). SQLite JSON1 features are optional and not a hard dependency.
- **Foreign Keys:** Enabled with appropriate `RESTRICT`/`SET NULL` semantics; transactions wrap high-impact writes.

💬 **Goal:** Avoid DB-specific JSON queries to keep shared-hosting portability. Use PHP services for validation and projection.

---

## 8. Draft → Commit → Published Snapshot Workflow

- **Editing:** Clone the active version into a draft, edit within the Studio UI, autosave periodically.
- **Commit:** Execute within a transaction; activate the new version, mark predecessors inactive, capture optional commit message/diff.
- **Resolver Pipeline:** During commit, resolve `[ref]`/`[query]` shortcodes recursively against the draft-state with cycle protection.
- **Snapshot:** Generate per-project JSON snapshot(s), written atomically (temp file → rename); path defaults to `var/snapshots/<project>.json`.
- **Consumption:** Frontend controllers, API endpoints, and exporters read exclusively from snapshots.

💬 **Scaling:** Start synchronously. Introduce Symfony Messenger queueing when commit throughput demands background processing.

---

## 9. Shortcodes & Resolver

**Syntax Highlights**
- `[ref @entity.field {link}]…[/ref]` – inline cross-reference, optional `{link}` metadata for front-end link builders.
- `[query {@entity?} select field[,field…] where field <op> value|@entity.field …]…[/query]`
  - Operators: `==`, `!=`, `<`, `>`, `<=`, `>=`, `~` (contains), `in` (array/comma list)
  - `where` accepts multi-field matches (`field1|field2`)
  - Values may reference `@entity.field`

**Behaviour**
- Persist shortcodes as markers; expand results during publish while leaving source content dynamic.
- Provide an `array` mode for raw data extraction.
- Emit translatable error codes: `ERR_REF_ENTITY_NOT_FOUND`, `ERR_QUERY_UNRESOLVABLE`, `ERR_QUERY_NO_RESULTS`.
- Implement resolver as a Twig TokenParser/Node rather than raw regex to stay robust.

💬 **Fieldsets:** JSON schemas may define computed fields (e.g., hidden aggregations executing a query). `@(self).field` resolves the current entity’s fields.

---

## 10. API Design

- **Read-Only API:** Serves published snapshots under `/api/v1/…`, mirroring frontend routes.
- **Optional Write API:** Instant-commit model (POST/PUT) with ability to generate draft+commit within a single request.
- **Auth:** Bearer API keys per user; multiple keys allowed with scope granularity.
- **Rate Limiting:** Configure limits per route/key/IP using Symfony Rate Limiter.

💬 **Determinism:** Serving the snapshot ensures the frontend and API deliver identical content without race conditions.

---

## 11. Exports (LLM Focus)

- **Primary Formats:** JSON / JSONL (canonical), optional YAML.
- **TOON (Optional):** Convert snapshots on the fly; JSON remains the source of truth.
- **Presets:** Configurable export profiles covering metadata, usage hints, policies, and field/entity selection.

💬 **Feature Flag:** Keep TOON behind a configuration toggle due to ecosystem volatility; converters run as separate services.

---

## 12. File Storage & Delivery

- **Storage Layout:** Store uploads under `var/uploads/<hash>/file.ext`; persist metadata in the DB (checksum, mime, owner, ACL).
- **Delivery Modes:**
  - Public assets served statically (CDN friendly).
  - Protected assets streamed through controllers with signed URLs/ACL checks (time-bound, project-specific).
- **Snapshots:** Deploy either as public files for speed or via controller for strict control; configure per project.

💬 **Hybrid Approach:** Allows future private areas without slowing down the public site.

---

## 13. UI/UX & Theming

- **Base Stack:** Tailwind CSS (precompiled), Stimulus controllers, Turbo for navigation, optional Alpine snippets where Stimulus is excessive.
- **Theming:** Global CSS variables + per-project overrides; avoid runtime Tailwind builds.
- **Editor:** CodeMirror with Markdown + JSON/Twig modes, autocomplete for `@entity` references, integrated via Importmap/Stimulus.
- **Template Packs:** Enable import/export of schema + frontend bundles; support reverting to pack defaults.
- **Theme Packs:** Install/activate from the admin UI (`.aavtheme` zip or Composer-provided pack); release builds compile assets ahead of time so operators avoid CLI.
- **In-App Guidance:** Provide inline documentation (tooltips, overlays, quick tips) for editors and admins.

💬 **Asset Pipeline:** Rely on Symfony AssetMapper + Tailwind bundle. No Node requirement on production hosts; assets prebuilt during release packaging.

---

## 14. Error Pages & Localisation

- **Error Pages:** Assign via entities in the `default` project (403/404/5xx). Provide sensible fallbacks.
- **Translations:** Use Symfony Translation domains; keep API responses English by default.
- **Bundled Languages:** German + English out-of-the-box; document the translation process for additional locales.

---

## 15. Security, Logging & Privacy

- **Roles:** User, Admin, Super-Admin; extendable. ACL-guard sensitive operations (hard delete, system settings).
- **Rate Limiter:** Protect login, API, and form submissions; provide configuration knobs via admin UI.
- **Logging:** Capture ERROR/WARNING/DEBUG/AUTH/ACCESS with retention policies. Anonymise IPs after 14 days.
- **GeoIP (Optional):** Enable MaxMind enrichment only when a valid key is configured; otherwise log `- -`.

---

## 16. Admin Features Without CLI

- **Setup Wizard:** Browser workflow covering environment diagnostics, folder permissions, database initialisation, admin creation, and secret generation.
- **Safe Mode:** Web UI for running Doctrine migrations/schema updates in a controlled fashion (Super-Admin only).
- **Maintenance:** Clear caches, rebuild snapshots, trigger exports, and inspect queue state directly in the UI.

💬 **CLI vs. UI:** Developers rely on CLI locally; production admins should manage everything through the browser.

---

## 17. Deletion & Retention Policy

- **Default:** Soft delete entities via inactive flags/version history.
- **Hard Delete:** Only via dedicated module, ACL-limited to Super-Admins.
- **History Maintenance:** Offer scheduled purge tools for old versions (per project settings).

---

## 18. Open Decisions / Upcoming Calls

1. **Snapshot Storage:** Default to public files for speed; expose per-project toggle to force controller delivery.  
2. **Write API Flow:** Keep instant commit as default; evaluate optional draft-first flow based on feedback.  
3. **Materialized Path Format:** Finalise path serialisation (`/parent/child`) and bulk reindex strategy for mass moves.  
4. **Export Presets:** Define default packs (Blog, Docs, Storytelling) with field lists and metadata.  
5. **Queue Adoption:** Determine thresholds for introducing Messenger workers (commit volume, export size, resolver load).  
6. **Hosting Strategy:** ✅ Rewrite-first deployment confirmed; root loader stays compatibility-only with installer warnings and hardening checklist (2025-10-30).  
7. **Roadmap Step 1:** ✅ Open questions answered and documented across feature outlines (2025-10-30).

---

## 19. Roadmap (MVP → v1)

**MVP**
- Implement core domain entities & repositories
- Draft → commit editor flow
- Resolver v1 (`[ref]`, `[query]` with `select/where`)
- Publish pipeline + snapshot writer (atomic)
- Frontend catch-all routing + error pages
- Read-only API serving snapshots
- JSON/JSONL exporter with minimal preset support
- Installer, authentication, basic rate limits

**v1**
- Template packs, relation management UI, diff viewer
- Write API (instant commit) + audit trails
- Protected file delivery with signed URLs
- Optional MaxMind integration
- Optional TOON export behind feature flag
- Admin tooling (cache clear, rebuild snapshot, run migrations via UI)

---

## 20. Notes & Miscellany

- **Testing & Debugging:** Feature flags (TOON, MaxMind, queue) must be toggleable with clear logs and deterministic error messages (validators reference field + constraint).
- **Documentation:** Keep `docs/dev/**`, `docs/user/**`, and `docs/codex/WORKLOG.md` aligned with implementation. Environment recap lives in `docs/codex/ENVIRONMENT.md`; helper scripts belong under `.codex/`.
- **Coding Practices:** Prefer graceful error handling over exceptions where possible; provide actionable logs.
- **Session Workflow:** Before and after coding, review `docs/codex/WORKLOG.md`, update open items, and ensure Markdown remains in sync with code decisions.

---

## 21. Canonical Decisions

- The framework remains **Symfony**, with first-class support for rewrite-based hosting and a managed root-index compatibility layer for constrained shared hosts.
- JSON snapshots are the **single source of truth**; TOON remains an optional export flavour.
- Hierarchy uses **materialized paths** for predictable tree operations.
- File delivery follows a **hybrid approach** (static where possible, signed controller for protected assets).
- The write API defaults to **instant commit**, with configuration hooks to introduce draft-first flows later.
