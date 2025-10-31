# Feat: Theming & Templating Guidance

Status: Draft  
Updated: 2025-10-31  
Scope: Roadmap Step 4 prep (Session 3)

## Objective
- Document a consolidated strategy for Twig template structure, theme overrides, and shared CSS utilities that respects the existing theme registry and asset pipeline.
- Capture implementation steps that unlock theme-aware layouts for both public projects and the admin studio without introducing a Node/PostCSS toolchain.
- Identify open questions that need resolution before development work begins.

## Current Context
- Theme discovery, activation state, and Twig path cascade (active theme → enabled modules → base templates) already exist (`TemplatePathConfigurator`).
- Asset rebuild flow (`app:assets:rebuild`, `AssetStateTracker`, `AssetPipelineRefresher`) mirrors theme/module assets into the core `assets/` tree and recompiles Tailwind/App CSS.
- The locked `base` theme acts as metadata only; default Twig layouts and shared CSS currently live under `templates/` and `assets/styles/`.
- Tailwind is provided by the Symfony Tailwind bundle; no Node/PostCSS pipeline is available in CI or production builds.
- Existing CSS imports cover fonts, icons, and illustrations but lack shared utilities, design tokens, and theme-aware layers.

## Template Strategy

### Layout Cascade
1. `templates/base.html.twig` – global HTML skeleton with base blocks (`head`, `body_class`, `header`, `main`, `footer`).
2. `templates/layouts/admin.html.twig` – admin shell extending `base`, wiring admin navigation and flash handling, exposes a dedicated `content` block for admin pages.
3. `templates/layouts/error.html.twig` – error shell extending `base`, keeps diagnostics slots available in dev while surfacing generic messaging in production.
4. `templates/layouts/project.html.twig` – public project shell extending `base`, renders the project’s primary content payload (front page included).
5. `templates/layouts/entity.html.twig` – public entity shell extending `base`, renders entity payloads via schema-provided Twig or a fallback presenter for visible keys/values.
6. `templates/partials/` – atomic components grouped by feature:
   - `partials/footer/*.html.twig`, `partials/head/*.html.twig`, `partials/header/*.html.twig`, `partials/main/*.html.twig` (base-block includes for modularity)
   - `partials/forms/fields/*.html.twig` (base fields)
   - `partials/forms/buttons/*.html.twig` (actions)
   - `partials/navigation/*.html.twig` (menu rendering)
   - `partials/sections/*.html.twig` (hero, cards, footers)
7. `pages/*.html.twig` – contains login-, admin-, error- and other static pages' `content`-block, extends corresponding shells.
8. Theme/module overrides follow the same structure so replacements remain predictable.

### Naming & Blocks
- Enforce lowercase, hyphenated filenames (`login-form.html.twig`, `menu-primary.html.twig`).
- Use `{% block %}` and `{% embed %}` to expose extension points instead of inline conditionals.
- Define a minimal contract per partial (documented via PHPDoc-style comment at the top or README table) with expected variables (`form`, `menuItems`, `projectSettings`).

### Theme Overrides
- Retain Twig path cascade while adding per-theme/module namespaces (`@Theme_slug/...`) for explicit imports in edge cases.
- Provide a `ThemeTemplateResolver` helper that attempts locale-prefixed templates (`de/footer.html.twig`) before falling back to the shared version.
- Document override search order in this note and link from future dev docs.

## Asset & CSS Strategy

### Tailwind Integration
- Keep `assets/styles/app.css` as the Tailwind entry point; introduce `assets/styles/imports.css` (generated) that aggregates:
  1. Core defaults (`assets/styles/base/*.css`) – ensure correct relative paths when moving existing files
  2. Active theme `theme.css`
  3. Enabled modules `module.css`
- Update `app:assets:rebuild` to refresh `imports.css` before invoking the Tailwind compiler. Reuse mirrored paths under `assets/themes/<slug>/` & `assets/modules/<slug>/`.

### Design Tokens & Utilities
- Define core design tokens in `assets/styles/base/tokens.css`:
  ```css
  :root {
      --color-primary: oklch(0.72 0.11 245);
      --color-primary-lighter: color-mix(in oklch, var(--color-primary) 75%, white 25%);
      --color-primary-darker: color-mix(in oklch, var(--color-primary) 75%, black 25%);
      --font-family-base: "Inter", ui-sans-serif, system-ui;
      --font-family-heading: "Raleway", ui-sans-serif, system-ui;
      --radius-base: 0.75rem;
      --spacing-unit: 0.25rem;
  }
  ```
- Supply complementary utilities in `@layer base` / `@layer components` files (`typography.css`, `layout.css`, `forms.css`) that map tokens to Tailwind utility classes using `@apply`.
- Ensure defaults remain usable without theme overrides (base theme = metadata only).

### Project & Theme Overrides
- Project-specific accents are provided via Twig by injecting a `<style>` block in the `<head>` when a project defines overrides:
  ```twig
  {% if project.themeOverrides %}
      <style>
          :root {
              --color-primary: {{ project.themeOverrides.colorPrimary|default('--color-primary') }};
          }
      </style>
  {% endif %}
  ```
- Lighter/darker variants rely on native CSS `color-mix` with the OKLCH color space; no PostCSS pre-processing required.
- Themes can override defaults by providing their own `theme.css` that redeclares variables and adds `@layer theme` utilities. Because Tailwind recompiles per rebuild, new utilities become available automatically.

### HTML Hooks
- All layouts add `data-theme="{{ activeTheme.slug }}"` and `data-locale="{{ app.request.locale }}"` to `<html>` so CSS variants and Stimulus controllers can branch without JS lookups.
- Document the expectation that themes provide a `[data-theme="<slug>"]` variant in `theme.css` if they need scoped overrides while retaining base fallbacks.

## Localization Approach
- Wrap user-facing strings in `trans` with consistent domains (`messages`, `forms`, `navigation`).
- Extend Twig loader lookup to try locale-prefixed directories (`de/footer.html.twig`, `en/footer.html.twig`) when present; fall back to the shared template if no locale folder exists.
- Encourage themes to ship localized variants only for content-heavy partials; otherwise rely on translation keys.
- Ensure translations for shared strings live in `translations/messages.{de,en}.yaml`; theme-specific domains can ship under `themes/<slug>/translations/` (same for modules) and be synced alongside assets during rebuild.

## Navigation & Menus
- Build a `MenuBuilder` service that:
  - Queries projects/entities via existing repositories.
  - Filters by visibility (`visible`, `show_in_navigation` flags).
  - Produces a normalized tree (`label`, `url`, `children`, `type`, `order`).
- Rendering strategy:
  - Primary navigation (top-level) partial `partials/navigation/main.html.twig`.
  - Sidebar partial `partials/navigation/sidebar.html.twig` renders descendants of the active top-level item (admin UI can swap styling).
  - Footer partial exposes slots for twig/html-content and user-managed links (including social icons); configuration stored globally in system scope and surfaced via admin UI.
- Admin vs. Public:
  - Admin layout reuses builder but adds capability checks; optionally injects an admin-specific menu definition to replace the login link with profile/actions entries.

## Implementation Plan
1. **Documentation**
   - Finalise this outline and cross-reference Worklog `Planned Implementations, Outlined Ideas`, align user and developer documentation (including theme developer docs) once implementation begins.
2. **Template Restructure**
   - Create layout/partial directories and migrate existing templates to the agreed naming + implement localization.
   - Add README summarising cascade and variable contracts.
3. **Menu Builder Service**
   - Implement builder + Twig helper, cover with integration tests (public + admin cases).
4. **Localization Enhancements**
   - Add locale-aware template resolver and update translation domains.
5. **CSS Foundation**
   - Introduce token/utilities files, generate `imports.css`, wire rebuild pipeline, and document theme override contract.
6. **Project-Level Overrides**
   - Implement Twig injection for project accent variables and persistence for user-configurable footer/social settings.
7. **Theme Toolkit**
   - Document expected `theme.css` structure, update theme manifests to flag available overrides, and extend admin UI (future).

## Decisions
- Locale-specific templates live under locale-prefixed directories (`templates/de/footer.html.twig`), falling back to the shared version when the locale folder is missing.
- Module-provided menu items reuse existing manifest priorities/order metadata; future navigation enhancements can introduce finer-grained weights if required.
- Footer/social content renders default fallbacks until project-level settings or entity-sourced content is available, so the UI can swap seamlessly once configuration forms ship.
- The admin layout ships with a two-panel pattern (top navigation + sidebar). Themes may adjust presentation (accordions, megamenus) by reshaping the generated tree, but the menu builder remains responsible only for producing hierarchy and metadata.

## Open Questions
- None at this stage; revisit once implementation uncovers additional constraints.

## Risks & Considerations
- CSS variable overrides rely on modern browser support for `color-mix`/OKLCH; document browser expectations and consider neutral fallbacks if primary hue overrides are unavailable.
- Introducing `imports.css` adds another generated artefact—ensure rebuild tooling cleans stale imports when themes/modules are removed.
- Template migration may break existing Twig lint tests; plan incremental updates with coverage adjustments.
- Admin and public navigation sharing the same builder must remain performant even with deep entity trees; caching must avoid leaking protected items into public scopes.
