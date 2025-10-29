# Module: Frontend Theming & Site Delivery (P1 | Scope: L)

**Status:** Draft – will iterate with design requirements.  
**Objective:** Enable modular theming for public-facing sites, including theme packs, template overrides, and project-specific styling.

## Capabilities
- Theme manager UI `/admin/themes` to install, activate, and configure theme packs.
- Theme packs include Tailwind config overrides, Twig layouts, components, and asset bundles.
- Support per-project theme assignment with fallback to global default.
- Allow theme-specific settings (colours, typography, layout toggles) stored as JSON and injected into Twig globals.
- Provide preview mode: render project with alternate theme without publishing.

## Architecture
- Theme packs distributed as ZIP or Composer package containing:
  - `theme.yaml` manifest (name, version, author, supported modules)
  - `templates/` overrides
  - `assets/styles/` partials
  - `config/tailwind.extend.js` (compiled to CSS via Tailwind build)
- Theme loader registers Twig namespace and AssetMapper paths at runtime.
- Modules can expose theme slots (e.g., Exporter status widget) by referencing named Twig blocks.

## Frontend Delivery
- Dynamic menus & navigation integrate via Navigation Builder module.
- Snapshot data rendered through schema-based Twig helpers.
- Cache busting via AssetMapper hashed filenames.
- CLI command `app:theme:build` to compile theme assets (batch builds for multiple themes).

## Implementation Steps
1. Theme manifest schema + loader service (activate/deactivate).
2. Admin UI for theme management + configuration forms.
3. Tailwind build pipeline per theme (build once per release).
4. Preview controller leveraging query param (`?theme=slug`) for admin testing.

## Open Questions
- How do we isolate Tailwind builds per theme without exploding build times?
- Should we allow runtime CSS variables injection for quick tweaks without rebuild?
- Do we require theme dependency version checks (modules requiring certain theme capabilities)?
