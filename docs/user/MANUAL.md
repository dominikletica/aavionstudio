# aavion Studio – User Manual

Status: Draft  
Updated: 2025-10-30

Welcome! This manual will guide administrators, editors, and integrators through installing, configuring, and using aavion Studio. The content below provides an overview of the documentation structure; detailed guides live under `docs/user/sections/`.

---

## 1. Getting started

- **Introduction & Concepts** – What makes aavion Studio different (schema-driven content, draft → commit, snapshots).
- **System Requirements** – PHP 8.2+, SQLite support, web server configuration (Apache/nginx/IIS) with rewrite or root loader fallback.
- **Web Server Configuration** – See [detailed hosting recipes](sections/getting-started/web-server-configuration.md) for Apache, nginx, and IIS.
- **Quick Installation** – Using the browser installer vs. manual configuration.
  - Visit `/setup` after uploading the release archive; follow the on-screen steps for diagnostics, environment, storage, admin, and summary.
  - The diagnostics panel highlights missing PHP extensions and writable directory issues with remediation hints.
- **Post-Install Checklist** – Create first admin, configure email, set up backups, enable modules.

---

## 2. Administration guide

- **Dashboard Overview** – Navigating the Admin Studio UI, notifications, search palette.
- **Modules** – Drop-in features live under `modules/` as `.aavmodule` bundles with update metadata; UI activation & update checks arrive with installer enhancements.
- **Projects & Settings** – Managing projects, locales, error-page entities (default project provides fallback Twig templates).
- **User & Access Management** – Creating users, assigning roles/permissions, managing API keys.
- **Maintenance Tools** – Cache, snapshot rebuild, queue monitoring, health checks.

---

## 3. Content authoring

- **Schema & Templates** – Selecting content models, understanding fields, previewing templates.
- **Draft Workflow** – Creating drafts, autosave, collaboration tips.
- **Commit & Publish** – Reviewing diffs, resolving validation errors, publishing snapshots.
- **Shortcodes & Resolver** – Using `[ref]` and `[query]`, handling errors, best practices.
- **Media Library** – Uploading files, managing metadata, embedding media.
- **Navigation & Menus** – Building site menus, visibility rules, sitemaps.

---

## 4. Site delivery & theming

- **Front-End Themes** – Activating theme packs, adjusting settings, preview mode.
- **Error Pages** – Assigning project-specific entities as error views with fallback Twig templates.
- **Custom Pages & Components** – Extending Twig templates, injecting modules.
- **Caching & Performance** – Understanding snapshot delivery, cache invalidation triggers.

---

## 5. Integrations & exports

- **Read API** – Consuming published snapshots via REST.
- **Write API (optional)** – Automating content creation with API keys & scopes.
- **Exporter Module** – Configuring presets, running JSON/JSONL/TOON exports.
- **Backup & Restore** – Scheduling backups, storing archives, restoring safely.

---

## 6. Troubleshooting & FAQ

- **Installer Issues** – Common errors in shared hosting environments.
- **Resolver Errors** – Interpreting error codes and fixing content.
- **Snapshot Problems** – Debugging missing data or outdated views.
- **Support Channels** – Where to file issues and contact the maintainer.

---

## 7. Documentation structure

- Detailed guides are stored in `docs/user/sections/` for each topic above.
- Keep this index updated as new features become available.
- Contributors should ensure screenshots, walkthroughs, and FAQs stay in sync with the UI.

Happy publishing!
