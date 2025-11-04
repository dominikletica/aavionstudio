# Feat: Admin Studio UI (Roadmap Step 4)

Status: Draft  
Updated: 2025-11-05  
Owner: Admin Studio strike team

The admin experience must feel like a first-class area of the product while sharing the same design system, Twig shell, and asset pipeline as the public site. Roadmap Step 4 builds on the existing Tailwind + Twig base (`templates/base.html.twig`, `templates/layouts/default.html.twig`, `assets/styles/**`) and extends it with navigation, layout, and interaction patterns that work for both admin and frontend contexts.

## Goals & Non-Goals

- Deliver a cohesive admin workspace reachable at `/admin`, including a dashboard landing view and discoverable navigation for all enabled modules.
- Reuse the existing layout (`base.html.twig` → `layouts/default.html.twig`) so the admin and frontend stay visually consistent; differences come from injected data and header/navigation variants.
- Add an `admin` CSS hook on the root `<html>` element (not on `<body>`) to support future admin-specific theming without diverging the templates.
- Provide a command palette, contextual help drawer, notifications, and modular sidebar/header actions while continuing to add reusable component partials for cross-page templating workflows.
- Keep Admin UI strings under the `admin` translation domain and respect the shared localisation/debug tooling already wired in `base.html.twig`.
- Ensure feature modules can seamlessly add admin functionality through manifest-driven navigation, component slots, and action registrations.
- Defer complex theme packs or visual regression tooling to later roadmap steps; Step 4 focuses on the base shell, interaction scaffolding, and feature-ready extension hooks.

## Shared Layout Architecture

### Base template contract
- `templates/base.html.twig` continues to own `<html>`/`<head>` and global assets. We will surface a new `html_classes` block/variable enabling layouts to append the `admin` class to the `<html>` element (body classes remain supported for page-specific styling).
- Maintain the existing import map inclusion and debug locale widget. Admin-specific scripts (e.g., command palette Stimulus controller) load via the shared `partials/head/importmap.html.twig`.

### Layout cascade
- Keep `layouts/default.html.twig` as the single entry shell for both frontend and admin pages. Perform a small refactor so common structure lives there, while `layouts/admin.html.twig` layers admin-specific context and variables on top.
- `layouts/admin.html.twig` configures:
  - `html_classes = ['admin']`
  - Header context (`admin_header_title`, navigation actions) passed to `partials/header/header.html.twig`.
  - Admin-specific navigation slots (global + sidebar) mapped from the navigation registry (see below).
- If a dedicated frontend shell becomes necessary later, introduce it in a focused refactor without fragmenting the current default layout.

### Header & footer variants
- The existing hero-style header (`partials/header/header.html.twig`) stays shared across admin and frontend; admin routes only adjust supplied data (logo, breadcrumbs, background image). Styling differences rely on the `admin` class and utility classes rather than bespoke variants.
- Footer content remains shared. Admin routes can inject contextual status blocks through existing footer slots, keeping markup consistent for theming engines to override when necessary.

## Navigation & Information Architecture

### Primary navigation
- Retain the global navigation partial (`partials/navigation/global.html.twig`) and feed it from a unified navigation builder that reads the module manifest registry.
- For admin routes, the primary navigation continues to live within the header region (matching the frontend appearance) but exposes admin-specific labels/icons. Modules can provide menu entries with capability requirements; the builder filters items per user context.

### Sidebar & workspace navigation
- Sidebar markup (`partials/navigation/sidebar.html.twig`) already exists. We will:
  - Support nested sections and badges for module-specific counts.
  - Allow modules to inject “workspace” panels (drafts, approvals, etc.) through the existing `sidebar_top` / `sidebar_bottom` blocks and optional data providers.
  - Render the sidebar within the shared two-column layout defined in `layouts/default.html.twig`, ensuring public pages can opt into the same structure when needed.

### Dashboard landing (`/admin`)
- Create `pages/admin/dashboard.html.twig` as the default admin canvas. Content blocks include:
  - “At a glance” metrics (snapshot status, draft throughput, integration health) rendered via cards.
  - Activity timeline (recent commits, audit entries, scheduled jobs).
  - Quick links (create draft, manage schemas, open docs).
  - System health summary sourced from existing maintenance services.
- Include at least one ApexCharts visualisation (e.g. draft status trend or snapshot recency) to validate the charting pipeline.
- Dashboard data loads through a dedicated controller (`AdminDashboardController`) that orchestrates existing repositories/managers; no bespoke API layer required in Step 4.

## Components & Interactions

### Command palette
- Stimulus or Alpine-based palette launched via `⌘K`/`Ctrl+K` and `/`.
- Data providers:
  - Route registry (modules tag routes as `searchable`).
  - Recent entities (hook into forthcoming Draft workflow services).
  - Global actions (clear cache, rebuild assets) subject to capability checks.
- Palette UI is a shared Twig partial so public pages can reuse it later (e.g., search, quick navigation). Styling relies on Tailwind utilities defined in `assets/styles/base/*.css`; when new variants are required, add reusable utility classes or expand `theme.css` tokens rather than hardcoding colours.

### Notifications & toasts
- Build on `partials/alerts/alerts.html.twig` to deliver two tiers:
  1. Inline flash messages sourced from Symfony sessions (already supported).
  2. Animated toast cards entering from the top-right, dismissible, and driven by Stimulus state to surface Messenger-driven updates or background task outcomes.
- Use the existing design tokens from `assets/styles/base/components.css` to keep the visual language consistent.

### Contextual help drawer
- Shared component triggered from header/help icons. Content relies on the existing JSON help provider introduced in the setup UI so release packages ship with embedded guidance.
- Extend the provider to let modules/themes contribute `help.json` files discovered under their root (respecting localisation cascade) and ensure interactive elements expose `data-help-key` attributes for inline tooltips.

### Modals & split panes
- Create shared modal/drawer partials with ARIA-compliant markup and Stimulus controllers for keyboard/focus handling. This unlocks admin dialogs and future frontend overlays simultaneously.

## Twig & Stylesheet Work

- Keep `layouts/default.html.twig` as the shared shell and tighten `layouts/admin.html.twig` so it simply augments context/classes rather than redefining structure.
- Update `base.html.twig` to accept `html_classes` and set `<html class="admin …">` (in addition to existing body class support) when the admin layout is active.
- Review and extend `assets/styles/base/layout.css`, `navigation.css`, and `utilities.css` to support the dashboard grid, sidebar stacking, command palette transitions, and toast animations—reusing patterns for frontend pages wherever practical. New colour tokens belong in `assets/styles/base/theme.css` (with dark-mode counterparts).
- Place single-purpose components under `templates/partials/components/`, group reusable UI assemblies under `templates/partials/ui/`, and keep admin-only composites in `templates/partials/admin/` for clarity.
- Reuse existing typography/icon assets (`fonts.css`, `icons.css`) and design tokens from `theme.css`; no additional font imports required for Step 4.

## Data & Service Integrations

- Navigation builder leverages the module manifest registry (already managed by the Asset sync tooling) and caches per locale/user.
- Dashboard controller aggregates:
  - Snapshot metadata (`SnapshotManager`).
  - Draft metrics, recent commits, audit log summaries, and asset rebuild progress.
  - Messenger queue/asset rebuild status (via existing maintenance services).
- Command palette backend endpoint returns JSON search results; reuse Symfony’s route collection and capability checks from `ProjectCapabilityVoter`.

## Accessibility & Internationalisation

- Command palette, sidebar, and notification drawers must support keyboard navigation, ARIA roles, and focus trapping.
- All new UI strings use translation keys in the `admin` domain with updates for both English and German catalogues.
- Maintain locale toggling compatibility with the debug widget already present in `base.html.twig`.

## Testing & Validation

- Extend existing functional suites (`tests/Controller/Admin/**`) with coverage for the dashboard route, navigation visibility per role, and command palette JSON endpoint.
- Add Stimulus integration tests (Symfony Panther or Turbo-driven tests) verifying navigation interactions, palette invocation, and help drawer toggling.
- Ensure Twig lint (`tests/Lint/TwigLintTest.php`) and asset rebuild suites (both already wired into the PHPUnit run) continue to pass after layout refactors.

## Documentation & Follow-Up

- Update `docs/dev/classmap.md` with new controllers/services introduced for navigation and dashboards.
- Extend `docs/dev/MANUAL.md` and `docs/user/admin-guide.md` (to be created) with navigation explanations once UI stabilises.
- Record implementation progress and outstanding tasks in `docs/codex/WORKLOG.md` during Step 4 execution.

## Implementation decisions & follow‑ups

- Seed dashboard cards and ApexCharts with a `dashboard-placeholder.json` dataset under `var/mock/` (mirrored to the build when needed). The controller reads from it until live telemetry hooks into the Draft/Snapshot services.
- Expose system maintenance shortcuts both through the command palette and a sidebar quick-action bar (cache rebuild, asset rebuild, commit draft). Quick actions use icon buttons with tooltips, are capability-aware, and remain easily extensible for future shortcuts (e.g., exports).
- Extend the help provider to auto-discover `help.json` files for active modules/themes using the same cascade we employ for templates/assets; manual registration hooks remain available for edge cases.
- Introduce project-level accent overrides when the project management module ships: project settings surface a `color_primary` override that injects a scoped `<style>` tag (when browsing that project or its admin views) to redefine `--color-primary` without forking themes.
