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
- `app_navigation_menu` (id, key, title, description, project_id, locale, settings JSON).
- `app_navigation_item` (menu_id, parent_id, label, url, entity_ref, visibility rules, order, icon).
- Versioning similar to content: drafts for menu changes with publish workflow (reuse draft/commit pipeline if feasible).

## Integration
- Modules can register default menus or add items (e.g., Exporter adds link in admin navigation).
- Support multi-language menus via per-locale instances or translation blocks.
- Provide API endpoint `/api/v1/navigation/{menu}` delivering JSON for SPA usage.

## Implementation Plan
1. Persistence layer + Doctrine mappings for menus/items.
2. Admin UI with tree component (Stimulus) for editing structure.
3. Renderer service for frontend (resolve entity slugs -> URLs).
4. Sitemap generator + cron/command integration.

## Open Questions
- Should menu drafts reuse the main Draft/Commit infrastructure or keep a simplified workflow?
- How do modules safely extend core menus without causing merge conflicts?
- Do we need per-user personalised menus (feature flag for future)?
