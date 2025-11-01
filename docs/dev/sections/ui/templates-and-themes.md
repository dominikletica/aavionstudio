# Templates & Themes

Status: Draft  
Updated: 2025-11-01

This guide explains how the templating stack is organised and how to extend or replace layouts when building custom themes or modules.

## 1. Directory structure

```
templates/
├── layouts/           # Base shells (admin, security, project, entity, installer)
├── partials/          # Reusable components (navigation, forms, feedback, overlays, tables…)
└── pages/             # Concrete page bodies grouped by domain (admin/, security/, installer/, …)
```

Theme and module overrides should mirror this structure (e.g. `themes/<slug>/templates/layouts/admin.html.twig`).

## 2. Layout cascade

- `templates/base.html.twig` is the root shell; it wires the HTML head, global header/alerts/footer partials, and exposes the `main` block.
- `layouts/default.html.twig` extends the root shell and provides the shared page scaffold (`content` block) plus optional sidebar hooks (`sidebar_top`, `sidebar_nav`, `sidebar_bottom`). The sidebar automatically renders when `sidebar_menu` (or `menu`) is provided.
- `layouts/entity.html.twig` builds on the default layout, adding the `entity_title` helper. Use this for entity-driven pages, error fallbacks, or any view that should inherit the standard sidebar/header cascade.
- `layouts/project.html.twig` currently just aliases the entity layout; future project-specific overrides can specialise `sidebar_*` blocks or headers.
- `layouts/admin.html.twig` also extends the default layout, injecting the admin header and relying on the same sidebar hooks (controllers pass `sidebar_menu` via `AdminNavigationTrait`).
- `layouts/security.html.twig` overrides the `main` wrapper to centre authentication flows while still benefiting from the shared title + sidebar contract.
- `layouts/installer.html.twig` renders the setup wizard shell, wrapping its content in a `<main>` element that contains the step navigation and page blocks.

Pages extend one of the layouts and render the actual content (`templates/pages/admin/users/index.html.twig`, `templates/pages/security/login.html.twig`, etc.).

## 3. Partial components

Partial templates live under `templates/partials/` and are grouped by feature:

- `partials/alerts/alerts.html.twig` – flash message stack injected by the root shell.
- `partials/header/header.html.twig` – full-width hero header with optional background image/logo, overlay heading (`header_heading`) and subtitle. Admin/project/entity layouts can pass `header_image`, `header_logo`, `header_heading`, and `header_subtitle` to customise the hero.
- `partials/navigation/*.html.twig` – global/header menus and sidebar sections.
- `partials/forms/fields/*.html.twig` – form inputs; use `{% include %}` in pages or embed in custom form themes.
- `partials/forms/buttons/*.html.twig` – button presets (`btn btn-primary`, etc.).
- `partials/feedback/*.html.twig` – legacy flash/alert snippets (replaced by the component collection below).
- `partials/components/*.html.twig` – reusable building blocks (buttons, alerts, cards, empty states) with Tailwind-ready styling and illustration slots.
- `partials/head/importmap.html.twig` – default importmap injection, can be overridden by themes.

When adding new partials, document expected context variables at the top of the file or in this guide.

### 3.1 Component partials

The component collection keeps installer/admin views lean while ensuring consistent styling. Available building blocks:

| Partial | Purpose | Notes |
|---------|---------|-------|
| `partials/components/button.html.twig` | Generic button element | Supports `variant`, `size`, `icon`, `badge`, anchor rendering (`tag: 'a'`) and full-width buttons. |
| `partials/components/alert.html.twig` | Inline alert / callout | Accepts `variant`, optional `illustration`, `title`, `description`, and an `actions` array (rendered via the button component). |
| `partials/components/card.html.twig` | Content card | Provides `eyebrow`, `title`, `subtitle`, `body`, optional `media`/`illustration`, call-to-action buttons and footer slot. Enable `interactive` for hover elevation. |
| `partials/components/empty_state.html.twig` | Empty state / onboarding panel | Centres an illustration with supporting copy and action buttons. Use `variant: 'muted'` for softer backgrounds. |
| `partials/components/illustration.html.twig` | Utility wrapper for CSS-based illustrations | Maps `name` to the generated `.illustration-*` class (e.g. `name: 'a-day-at-the-park'`). Variants (`sm`, `md`, `lg`) adjust the minimum height. |
| `partials/components/table.html.twig` | Data table shell | Handles toolbar slots, zebra/compact variants, empty states, pagination summary, and optional custom cell templates via `column.template`. |
| `partials/components/modal.html.twig` | Modal dialog shell | Provides backdrop, header (title/subtitle), body slot, and footer actions. Hooks (`data-action`) can be wired to Stimulus later. |
| `partials/components/drawer.html.twig` | Drawer/off-canvas panel | Mirrors the modal API for side panels (`side: 'left'|'right'`). |
| `partials/components/section_heading.html.twig` | Section header | Consistent heading + description + action buttons for dashboard sections. |

Components accept an `attributes` map for custom HTML attributes. Example:

```twig
{% include 'partials/components/card.html.twig' with {
    eyebrow: 'Project',
    title: project.name,
    subtitle: project.slug,
    body: render_markdown(project.summary),
    actions: [
        {
            label: 'View details',
            tag: 'a',
            url: path('admin_project_show', { id: project.id }),
            variant: 'secondary'
        },
        {
            label: 'Open',
            tag: 'a',
            url: path('project_overview', { slug: project.slug }),
            icon: 'arrow-right'
        }
    ]
} only %}
```

### 3.2 Form field partials

Form controls now ship with dedicated Twig snippets:

- `partials/forms/fields/input.html.twig` – text-based inputs (`type` defaults to `text`).
- `partials/forms/fields/textarea.html.twig` – multi-line input with `rows`, `help`, and state handling.
- `partials/forms/fields/select.html.twig` – dropdown selector driven by an `options` array.
- `partials/forms/fields/checkbox.html.twig` – checkbox with optional description copy.
- `partials/forms/fields/switch.html.twig` – accessible toggle switch matching the Tailwind tokens.

Each field accepts `label`, `help`, `state`, `required`, and additional HTML attributes. Combine them with the component buttons/card partials to assemble full forms and tables quickly.

### 3.3 Component showcase route

Developers can preview the base component library at `/_themedemo` (handled by `App\Controller\DemoController`). The page renders buttons, alerts, cards, tables, form fields, empty states, typography, and illustration helpers, plus interactive samples:

- Stimulus controller (`data-controller="themedemo"`) drives a live counter to show how to wire JS actions to Tailwind UI.
- A Turbo frame (`turbo-frame#themedemo_tip`) lazy-loads rotating guidance from `_theme_demo_tip`.
- The `codemirror` Stimulus controller upgrades textareas into language-aware editors.
- Alpine.js powers lightweight tab toggles to demonstrate micro-interactions without custom controllers.

Use it when building new themes or verifying Tailwind overrides—clone the template under `templates/pages/demo.html.twig` into your theme (or override the controller) to ship custom showcases.

## 4. Theme tokens & Tailwind utilities

- Theme tokens are defined in `assets/styles/base/theme.css` using Tailwind’s `@theme` directive. They expose colors, typography, radii, shadows, transitions, etc.
- Shared component layers (`assets/styles/base/*.css`) rely exclusively on Tailwind utilities plus the tokens; you can safely use any Tailwind class in Twig templates.
- Additional helper utilities live in `assets/styles/base/utilities.css` (`.text-primary`, `.bg-surface`, `.badge`, `.alert--*`, `.nav-*`, `.table`, `.modal`, `.skeleton`, etc.).
- Rebuild the pipeline with `php bin/console app:assets:rebuild --force` whenever theme CSS changes; the command now clears `public/assets/` before regenerating bundles.

## 5. Creating/overriding templates

1. **Mirror the structure**: place overrides in `themes/<slug>/templates/...` using the same relative path as the core template.
2. **Register the theme**: ensure the manifest (`theme.php` / `theme.yaml`) is discovered so AssetMapper mirrors `themes/<slug>/assets/` and Twig finds the templates.
3. **Regenerate imports**: run `php bin/console app:assets:rebuild --force` to refresh `imports.css` and rebuild Tailwind/importmap/asset-map output.
4. **Navigation menus**: if a theme needs different menus, override `partials/navigation/*.html.twig` or provide custom menu arrays in controller traits.

## 6. Installer & error states

- Installer steps live under `templates/pages/installer/`. Add new steps by creating a new page template and pointing the controller to it.
- System/error pages should follow the same `layouts/ + pages/` convention; when adding them, update this guide and the developer manual.

## 7. Testing & linting

- Twig syntax: `php bin/console lint:twig templates/`.
- Rendering expectations are covered in controller functional tests (e.g. login alerts, installer warnings). When templates change, update the corresponding tests instead of reintroducing legacy markup.

## 8. Next steps

- Expand partial coverage (breadcrumbs, tabs, cards) as modules/pages start using them.
- Document navigation builder conventions once the menu service ships.
- Keep this file updated whenever the structure or available components change.

## 9. Error pages

- Application errors now render through `pages/error/...` templates using `layouts/entity.html.twig`.
- Add overrides by placing a template with the status code under `templates/pages/error/<code>.html.twig` or by mapping a custom template path via project settings (`config/app/projects.php` &rarr; `settings.errors`).
- Debug mode automatically exposes exception details and a trimmed stack trace; production renders a generic message.
- If rendering fails, Symfony's built-in error controller is used as a fallback.
