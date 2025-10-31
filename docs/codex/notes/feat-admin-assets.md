# Feat: Admin UI Assets & Theming

**Status:** In progress – baseline asset stack implemented, follow-up theming tasks pending for Roadmap Step 4.

## Objectives (Validated)
- Provide a consistent icon pipeline via CSS classes (e.g. `<i class="ti ti-icon"></i>`) and future Twig helpers. ✅ Classes provided in `assets/styles/icons.css`; Twig helper still TODO.
- Host base typography (Inter for body copy, Raleway for headings) locally with room for theme overrides. ✅ Fonts stored under `assets/fonts/{inter,raleway}` with `@font-face` declarations in `assets/styles/fonts.css`.
- Curate additional UI assets consumable through importmap/AssetMapper without Node. ✅ Tabler icon webfont, unDraw illustrations, Alpine.js, and ApexCharts bundled locally.

## Decisions & Implementation
1. **Icon delivery**
   - Adopted Tabler webfont (WOFF/WOFF2) hosted in-repo; CSS classes exposed via `assets/styles/icons.css`.
   - Tailwind entry (`assets/styles/app.css`) imports the icon stylesheet so classes are globally available.
   - Twig helper not yet implemented—track as follow-up to offer `icon('ti-brand-tabler')` sugar.

2. **Base typography**
   - Inter and Raleway variable fonts vendored under `assets/fonts/`, with `assets/styles/fonts.css` defining `@font-face` aliases (`font-family: "Inter"` / `"Raleway"`).
   - Raleway italic remains unavailable in bundled source; revisit if a reliable variable italic appears.
   - Themes can override by supplying additional `@font-face` blocks or alternate stacks; document pattern in upcoming theming guide.

3. **Illustrations**
   - Selected unDraw set, mirrored in `assets/illustrations/undraw`.
   - Generated utility classes in `assets/styles/illustrations.css` (`.illustration-...`) mapping to individual SVGs for use in empty states and marketing blocks.
   - VS Code lint errors resolved by using direct relative URLs; file now editor-friendly.

4. **CSS utilities & design tokens**
   - Tailwind still primary utility source (via `assets/styles/app.css`). Custom design tokens/utility layers for shared Admin/Frontend components remain TODO for Roadmap Step 4 implementation.

5. **Optional JS assets**
   - Alpine.js and ApexCharts stored under `assets/js/` with importmap entries and global bootstrap in `assets/bootstrap.js`.
   - Future components can import these directly; consider lazy loading when building dashboards to keep baseline lightweight.
6. **Manifests & Defaults**
   - Module and theme manifests now carry a `description` field for richer admin listings and documentation.
   - Added a locked `base` theme (mirroring the `core` module) that ships repository fonts/icons and acts as the default asset anchor.

## Testing & Tooling
- Module/theme manifests drive auto-discovery: `modules/*/module.php` and `themes/*/theme.{php,yaml}` hydrate registries consumed by asset sync/rebuild tooling.
- Added `app:assets:sync` console command to mirror discovered `modules/*/assets` and `themes/*/assets` into `assets/{modules,themes}` so AssetMapper/ImportMap builds see third-party bundles.
- Added state-aware pipeline refresher (`AssetPipelineRefresher`) + tracker (`AssetStateTracker`) with consolidated `app:assets:rebuild` command; service runs sync → importmap → Tailwind (minified in prod) → asset-map → cache clear/warmup, and persists hashes under `var/cache/assets-state.json`.
- Introduced asynchronous rebuild workflow via `AssetRebuildScheduler` (`App\Message\AssetRebuildMessage` routed to Messenger). UI flows can call `schedule(force: true)` to queue rebuilds without CLI access.
- PHPUnit lint suite (`tests/Lint/InitPipelineTest.php`) now exercises `app:assets:rebuild --force` before database/messenger setup so automated builds detect regressions early.
- Twig lint suite (`tests/Lint/TwigLintTest.php`) validates `templates/`, `themes/*/templates`, and `modules/*/templates` via `lint:twig`.
- `bin/init` runs `app:assets:rebuild --force` for a single-source asset pipeline during environment bootstrap.
- Admin console (`/admin/system/assets`) surfaces rebuild buttons (queue vs. run now) with live checksum snapshots for debugging.
- `TemplatePathConfigurator` rebuilds Twig search order per request: Active Theme → enabled module templates (priority desc, slug asc) → base templates, ensuring drop-in overrides behave predictably.
- Module/theme managers respect locked manifests, single active theme invariant, and trigger rebuild scheduling on state change.

## Runtime Rebuild Flow
- Theme/module installations should call `AssetRebuildScheduler::schedule()` after extraction; checksum comparison prevents redundant rebuilds.
- Admin UI exposes queue/synchronous rebuild actions backed by the scheduler; workers process background jobs via Messenger.
- Direct filesystem edits (modules or themes) invalidate the checksum so the next scheduled run rebuilds automatically.

## Remaining Tasks
- Define shared CSS utility layer (tokens/components) and document override strategy for themes.
- Provide developer guide covering asset contribution workflow (downloads, naming, testing, running `app:assets:sync` / `app:assets:rebuild`).
- Hook upcoming installer/upload flows into `AssetRebuildScheduler::schedule()` so ZIP imports trigger rebuilds automatically once those features land.
