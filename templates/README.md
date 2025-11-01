# Twig Templates

Status: Draft
Updated: 2025-10-31

Shared templates follow a layered cascade to enable theme overrides and keep presentation modular. Direct HTML inside controllers is avoided in favour of extendable layouts and partials.

## Directory map

- `layouts/` – top-level shells extending `base.html.twig` for admin, project, entity, error, and authentication flows.
- `partials/` – reusable molecules grouped by domain (`partials/navigation/…`, `partials/forms/…`, `partials/feedback/…`).
- `pages/` – discrete page bodies that extend the relevant layout (`pages/security/login.html.twig`, `pages/admin/users/index.html.twig`).
- `emails/` – plaintext/HTML emails (no layout inheritance by default).
- `installer/` – setup wizard scaffolding kept intentionally minimal.

Themes and modules mirror this structure when overriding templates. See `docs/codex/notes/feat-theming-templating.md` for the cascade and variable contract.
