# Feat: Frontend Delivery & Rendering (P0 | Scope: L)

**Status:** Draft – will be refined once schema and snapshot layers stabilise.  
**Goal:** Render published content to the public site using Twig templates, navigation menus, and snapshots as the data source.

## Deliverables
- Catch-all controller under `src/Controller/Frontend` that resolves project + path, pulls data from `SnapshotReader`, and renders Twig templates bound to schema definitions.
- Error page handling (403/404/5xx) driven by entity assignments; fallback to static templates when mapping missing.
- Layout composition using theme module (if active) or default Twig layouts.
- Dynamic meta tags (title, description, OG tags) derived from snapshot metadata and schema fields.
- Multi-locale support: map `/en/...` vs `/de/...` based on project configuration.

## Integration Points
- Navigation Builder provides menus consumed by base layout.
- Schema/Template system supplies Twig templates referenced per entity type.
- Snapshot manager ensures fast lookup; caching layer adds HTTP caching headers.
- Feature flags allow preview mode (render draft via query param) for admins.

## Implementation Steps
1. Implement routing strategy (project resolver + slug path) with cached lookup of route map.
2. Build `SnapshotReader` helpers for entity, listing, and related-content fetch.
3. Create base Twig layout with slots for navigation, breadcrumbs, content, footer.
4. Wire schema-based template resolution (e.g., `schema_render(entity)`).
5. Handle error pages and preview mode toggles.

## Decisions (2025-10-31)
- Route resolution stays schema/slug driven; exotic routes rely on alias tables rather than custom controllers.
- Preview mode requires authenticated users with the `content.preview` capability; share links use short-lived signed tokens layered on top.
- JSON-LD helpers are opt-in per schema so templates decide when to emit structured data.
