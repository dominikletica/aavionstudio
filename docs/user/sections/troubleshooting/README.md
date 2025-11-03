# Troubleshooting & FAQ

Status: Draft  
Updated: 2025-10-29

This chapter captures common runtime problems and how to fix them quickly.

## Page fails to render or shows raw HTML

When the frontend or `/setup` responds with an unstyled HTML dump, the asset pipeline probably failed to build:

1. **Check the logs**  
   - `var/log/dev.log` (or `prod.log`) will contain lines tagged with `[assets]`.  
   - Typical root causes:
     - Tailwind CLI download failed (e.g. no internet access, unsupported platform).  
     - PHP extensions required by Tailwind (e.g. `ext-zip`) or the installer (`ext-intl`, `ext-sqlite3`, `ext-fileinfo`) are missing.

2. **Verify the asset directory**  
   - `public/assets/entrypoint.app.json` must exist alongside `public/assets/styles/app-*.css` and `public/assets/app-*.js`.  
   - If those files are missing, run `php bin/console app:assets:rebuild --force` from the project root.

3. **Review system requirements**  
   - Ensure the server meets the [system requirements](../../MANUAL.md#1-getting-started), including PHP extensions and write permissions for `var/` and `public/assets/`.

## Installer issues

Document permissions, PHP extension requirements, and rewrite configuration troubleshooting here.

## Resolver error codes

Explain how to interpret resolver errors and unblock content issues.

## Snapshot / cache inconsistencies

Add guidance for clearing caches and forcing snapshot rebuilds.

## Media upload failures

Capture common causes (file size limits, MIME detection, missing `ext-fileinfo`) and remedies.

## Backup / restore pitfalls

List required files, database dumps, and safe restore procedures.

## Support channels

Provide links to the issue tracker, contact information, and diagnostic checklists.
