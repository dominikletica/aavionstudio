# Module: Frontend Theming & Site Delivery (P1 | Scope: L)

**Status:** Draft – will iterate with design requirements.  
**Objective:** Enable modular theming for public-facing sites, including theme packs, template overrides, and project-specific styling.

## Capabilities
- Theme manager UI `/admin/themes` to install, activate, and configure theme packs.
- Theme packs include Tailwind config overrides, Twig layouts, components, and asset bundles.
- Support per-project theme assignment with fallback to global default.
- Allow theme-specific settings (colours, typography, layout toggles) stored as JSON and injected into Twig globals.
- Provide preview mode: render project with alternate theme without publishing.

## Theme Pack Structure
```
my-theme.aavtheme
├── theme.yaml (or theme.php)
├── templates/
│   ├── base.html.twig
│   └── components/
├── assets/
│   ├── styles/theme.css
│   └── controllers/theme_controller.js
└── previews/thumbnail.png
```
- `theme.yaml` fields:
  ```yaml
  name: "Aurora"
  description: "Premium marketing theme with hero builder"
  slug: "aurora"
  version: "1.2.0"
  author: "aavion"
  repository: "https://github.com/aavion/themes-aurora"
  compatibleModules:
    - navigation >=1.0
  settings:
    - key: "accent_color"
      type: "color"
      default: "#0ea5e9"
  ```
  - `description` surfaces friendly copy in admin lists; keep concise.
  - `repository` allows the admin UI to surface latest release info via metadata JSON (offline friendly – fetched during packaging, cached locally).
  - The built-in `base` theme (slug `base`) ships with the repository and remains locked/enabled to guarantee default templates/assets.

### Discovery & Development Layout
- Unpacked themes live under `/themes/<slug>/` during development and ship a manifest (`theme.php` returning an array/`ThemeManifest` or `theme.yaml`). `ThemeDiscovery` hydrates `ThemeRegistry` so downstream tooling (assets, Twig lint, UI) sees all available themes without manual registration.
- Their assets (`/themes/<slug>/assets`) are mirrored into `/assets/themes/<slug>/` via the `app:assets:sync` command so AssetMapper/ImportMap builds pick them up without adding new root paths.
- Modules follow the same pattern: any `/modules/<slug>/assets` directory is mirrored into `/assets/modules/<slug>/`.
- Lint/Test pipelines invoke `app:assets:rebuild --force` (which runs a sync under the hood) before running Tailwind/AssetMapper to avoid stale mirrors.

## Architecture
- Theme packs distributed as ZIP or Composer package containing:
  - `theme.yaml` manifest (name, version, author, supported modules)
  - `templates/` overrides
  - `assets/styles/` partials
  - `config/tailwind.extend.js` (compiled to CSS via Tailwind build)
- Theme loader registers Twig namespace and AssetMapper paths at runtime.
- Modules can expose theme slots (e.g., Exporter status widget) by referencing named Twig blocks.
- Twig lookup cascade: Active Theme → enabled module templates (sorted by priority desc, slug asc) → base templates (`templates/`). Themes can override module-provided views; missing templates fall back automatically.

### Loader Sequence
1. Read manifest metadata; ensure slug unique.
2. Register Twig namespace `@Theme_<Slug>`.
3. Inject AssetMapper paths for precompiled CSS/JS.
4. Apply settings defaults to `project.theme_settings` when activated.
5. Fetch optional `update.json` from repository metadata (bundled with the theme) to inform administrators about available updates without live network calls.

## Frontend Delivery
- Dynamic menus & navigation integrate via Navigation Builder module.
- Snapshot data rendered through schema-based Twig helpers.
- Cache busting via AssetMapper hashed filenames.
- CLI command `app:theme:build` to compile theme assets (batch builds for multiple themes).

### Admin UX
- Theme index: cards showing preview image, status (Active/Available/Update Available).
- Install modal accepts `.aavtheme`; progress indicator handles upload/unpack, surfaces validation errors.
- Settings form auto-generates fields based on manifest definitions (color picker, toggle, select).
- Preview toggle applies theme for current admin session without affecting visitors.
- Update checker compares packaged manifest version with repository metadata; provides download button when newer release detected (serving ZIP from specified repo, no CLI needed).

## Implementation Steps
1. Theme manifest schema + loader service (activate/deactivate).
2. Admin UI for theme management + configuration forms.
3. Tailwind build pipeline per theme (build once per release).
4. Preview controller leveraging query param (`?theme=slug`) for admin testing.
5. (TODO) Hook installer/upload routines into the rebuild scheduler so ZIP installs queue asset recompilation automatically.

## Asset Build Strategy
- Build pipeline caches Tailwind output per theme using manifest checksum.
- Release packaging pre-renders `public/themes/<slug>/theme.css` and JS controllers.
- Admin-initiated rebuild allowed for custom themes uploaded post-release; runs via background job with progress notifications.
- Rebuild executed via PHP service (`ThemeBuildService`) invoking Tailwind bundle compiler programmatically—no shell access required. Fallback to cached CSS when rebuild queue running to avoid downtime.
- Runtime installs trigger `AssetRebuildScheduler::schedule()` which dispatches an asynchronous pipeline rebuild (`app:assets:rebuild`) covering sync → importmap → Tailwind (minify in prod) → asset-map → cache warmup.
- Admin UI exposes a manual “Rebuild Assets” action wired to the same scheduler for filesystem edits or emergency refreshes.
- Theme activation state (+ metadata) persists in `app_theme_state`, mirroring the module workflow; synchronisers keep DB and manifests in sync so install/update routines can share the same code paths.

## Decisions (2025-10-30)
- `app:theme:build` processes themes sequentially and caches artefacts so unchanged packs skip rebuilds.
- Runtime CSS-variable overrides are supported for quick tweaks stored in the database, reducing rebuild pressure.
- The theme loader enforces optional capability/version constraints declared in `theme.yaml` and surfaces warnings during activation.
