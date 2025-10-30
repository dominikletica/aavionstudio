# Module: Navigation Builder (P1 | Scope: M)

**Status:** Draft – specifics may change with UI prototyping.  
**Purpose:** Provide dynamic site navigation management, including menus, sitemaps, and front-end integration hooks.

## Features
- Admin UI `/admin/navigation` to create/manage menus (primary, footer, contextual).
- Menu items can reference entities, external URLs, or resolver queries.
- Drag-and-drop ordering with depth constraints and visibility rules (per role, per locale).
- Auto-generate sitemaps (XML/JSON) from menu definitions + entity visibility flags.
- Frontend helper Twig functions (`navigation_render('primary')`) returning structured arrays for theme integration.

## Data Model
- `app_navigation_menu` (id, key, title, description, project_id, locale, settings JSON, type).
- `app_navigation_item` (menu_id, parent_id, label, url, entity_ref, visibility rules, order, icon, meta JSON).
- `app_navigation_version` (menu_id, version, payload JSON, published_by, published_at).
- `app_navigation_draft` (menu_id, payload JSON, updated_by, updated_at) for autosave.
- Versioning similar to content: drafts for menu changes with publish workflow (reuse draft/commit pipeline if feasible).

## Integration
- Modules can register default menus or add items (e.g., Exporter adds link in admin navigation).
- Support multi-language menus via per-locale instances or translation blocks.
- Provide API endpoint `/api/v1/navigation/{menu}` delivering JSON for SPA usage.
- Manifest injection example:
  ```yaml
  navigation_injections:
    - menu: "admin"
      after: "content"
      priority: 200
      item:
        label: "Exports"
        route: "aavion_admin_exporter"
        capability: "export.run"
  ```
- Merge algorithm orders injections by priority, then by declared anchor (`before`/`after`), preventing duplicates via route comparison.

## Implementation Plan
1. Persistence layer + Doctrine mappings for menus/items.
2. Admin UI with tree component (Stimulus) for editing structure.
3. Renderer service for frontend (resolve entity slugs -> URLs).
4. Sitemap generator + cron/command integration.
5. Draft/commit integration with change diff preview (added, moved, removed items highlighted).

## Admin UI
- Tree component supports drag/drop with keyboard accessibility (space to pick up, arrows to move).
- Detail inspector exposes localized labels, visibility (role, capability, locale), scheduling (publish window).
- Preview pane renders menu using active theme styles; toggles between desktop/mobile view.
- Publish modal summarises changes and requires commit message.

## Renderer & API Notes
- Renderer builds nested arrays with `children`, `isActive`, `url`, `target`.
- API endpoint supports `?locale=` and `?preview_token=` for draft preview.
- Sitemap generator respects visibility rules and outputs `lastmod` from snapshot metadata.

## Decisions (2025-10-30)
- Menu updates reuse the shared Draft/Commit workflow for consistency with content publishing.
- Module-supplied menu items register through manifest hooks with priority/merge rules to avoid collisions.
- Personalised menus stay deferred behind a future feature flag; launch focuses on shared navigation.
