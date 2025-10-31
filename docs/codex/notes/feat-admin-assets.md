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

## Testing & Tooling
- PHPUnit lint suite (`tests/Lint/InitPipelineTest.php`) replays the init pipeline (asset cleanup, importmap install, Tailwind build, AssetMapper compile, DB setup, cache warmup) to catch asset regressions.
- Twig lint suite (`tests/Lint/TwigLintTest.php`) validates `templates/` (and `templates/themes` when present) via `lint:twig`.
- `bin/init` now adds `--minify` to Tailwind when targeting `prod`, ensuring release builds ship compressed CSS.

## Remaining Tasks
- Implement Twig helper for icons and update documentation with usage examples.
- Define shared CSS utility layer (tokens/components) and document override strategy for themes.
- Provide developer guide covering asset contribution workflow (downloads, naming, testing).
