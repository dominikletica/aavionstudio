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
- `partials/header/header.html.twig` – global header with menu + action hooks.
- `partials/header/header.html.twig` renders a full-width hero header with optional background image/logo, overlay heading (`header_heading`) and subtitle. Admin/project/entity layouts can pass `header_image`, `header_logo`, `header_heading`, and `header_subtitle` to customise the hero.
- `partials/navigation/*.html.twig` – global/header menus and sidebar sections.
- `partials/forms/fields/*.html.twig` – form inputs; use `{% include %}` in pages or embed in custom form themes.
- `partials/forms/buttons/*.html.twig` – button presets (`btn btn-primary`, etc.).
- `partials/feedback/*.html.twig` (planned) – alerts, toasts, badges (currently exposed via CSS utility classes).
- `partials/head/importmap.html.twig` – default importmap injection, can be overridden by themes.

When adding new partials, document expected context variables at the top of the file or in this guide.

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
