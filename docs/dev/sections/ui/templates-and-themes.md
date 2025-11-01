# Templates & Themes

Status: Draft  
Updated: 2025-10-31

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

- `layouts/base.html.twig` provides the HTML skeleton with importmap, body/main slots, and the `data-theme` attribute.
- `layouts/admin.html.twig` extends the base layout and expects `primary_menu` / `sidebar_menu` arrays (constructed via `AdminNavigationTrait`).
- `layouts/security.html.twig` wraps authentication flows in a centered panel.
- `layouts/installer.html.twig` renders the setup wizard shell with the step navigation.
- Additional layouts (project/entity) can be introduced following the same pattern.

Pages extend one of the layouts and render the actual content (`templates/pages/admin/users/index.html.twig`, `templates/pages/security/login.html.twig`, etc.).

## 3. Partial components

Partial templates live under `templates/partials/` and are grouped by feature:

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

- Add documentation for dedicated error pages once implemented.
- Expand partial coverage (breadcrumbs, tabs, cards) as modules/pages start using them.
- Keep this file updated whenever the structure or available components change.
