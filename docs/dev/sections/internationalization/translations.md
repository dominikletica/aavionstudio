# Translations & Locale Cascade

Status: Draft  
Updated: 2025-11-04

This guide summarises how aavion Studio discovers locale resources, resolves fallback catalogues, and keeps translations deterministic across themes, modules, and the core application.

---

## Key conventions

- Use dot-delimited keys in the form `namespace.section.token`.  
  Examples: `installer.environment.form.instance_name`, `ui.action_overlay.network_error`, `admin.users.table.columns.email`.
- Keep placeholders explicit (`%email%`, `%date%`); translate sentences, not HTML fragments. Twig templates should always call `|trans` with the key and parameters.
- Validator messages live under `translations/validators.<locale>.yaml` and must mirror any new constraint messages introduced in PHP.
- Date/time formats are exposed via `region.date.*` and `region.datetime.*` keys. Twig templates (and future JS helpers) read the format string from translations rather than hard-coding `Y-m-d`.
- Global role names resolve via `security.roles.<ROLE_NAME>`. Core roles ship with English/German entries; modules registering additional roles must provide matching keys in their own catalogues.

---

## Resource cascade & caching

1. **Active theme** – The currently active theme (as per `app_theme_state`) wins. The translator looks for files under `<theme>/translations/`.
2. **Enabled modules** – Next, the cascade adds translations from every module whose manifest is marked `enabled=1` in `app_module_state`. Modules are processed by manifest priority (highest first). Each module may provide a `translations/` directory alongside its assets.
3. **Fallback/base theme** – If the active theme is not the built-in `base` theme, the system also merges translations from the enabled `base` theme to guarantee default wording.
4. **System catalogue** – Finally, the core `translations/` directory fills in any remaining gaps.

Only active themes and modules participate in the cascade. The state comes from the same database tables that drive `app:assets:sync`, so disabling a module or theme immediately removes its translations after cache warm-up. Collisions respect the order above—the first definition encountered for a key wins; later catalogues skip duplicates.

The merge result is cached per locale (`CatalogueManager`) using file modification timestamps for invalidation. The translator receives the merged messages via the native “array” loader, so Symfony’s fallback logic still applies.

---

## Locale discovery

- `LocaleProvider` scans the same directories (active theme, enabled modules, base theme, core) to build the list of available locales.
- Supported locales are cached per request lifecycle; the fallback remains `en`.
- The `LocaleSubscriber` resolves a user’s locale in this order: explicit debug override → authenticated user preference → browser `Accept-Language` → system default (`core.locale`) → fallback (`en`).
- Developers can toggle locales or view translation keys via the debug footer dropdown (only in `APP_DEBUG` environments).

---

## Adding translations

1. **Theme authors** place locale files under `themes/<slug>/translations/`. Use the same file naming scheme as the core catalogues (`messages.<locale>.yaml`, `validators.<locale>.yaml`, etc.).
2. **Module developers** mirror the structure under `modules/<slug>/translations/`. Ensure the manifest is enabled so the cascade picks it up.
3. **Core contributors** continue to add/maintain keys under `translations/`, keeping English and German catalogues in lockstep.
4. After adding files, run `php bin/phpunit`—there’s coverage ensuring the cascade obeys the priority order and ignores disabled modules/themes.

Remember to update the worklog, manuals, and validator catalogues whenever new keys are introduced.
