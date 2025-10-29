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

## Open Questions
- Do we need per-project routing customisation (e.g., custom controllers for certain types)?
- How should we guard preview mode so only authenticated admins can access draft views?
- Should we embed structured data (JSON-LD) automatically based on schema metadata?
