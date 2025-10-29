# Developer Manual

This manual is the entry point for development workflows. Additional deep dives live under `docs/dev/sections/`.

## Environment Bootstrap

- Run `bin/init_repository` after cloning. It installs Composer dependencies, refreshes importmap/Tailwind assets, prepares SQLite databases, ensures Messenger transports, and rebuilds caches.
- Environment variables: `APP_ENV=dev`, `APP_DEBUG=1`, `DATABASE_URL=sqlite:///%kernel.project_dir%/var/system.brain`. Place project-specific overrides in `.env.local`.

## Project References

- Contributor guidelines: `AGENTS.md`
- Worklog & session notes: `docs/codex/WORKLOG.md`
- Environment recap: `docs/codex/ENVIRONMENT.md`
- Concept outline: `docs/codex/notes/OUTLINE.md`
- Class map: `docs/dev/classmap.md` (must list all callable entry points; keep in sync with code)

## Next Steps

- Add subsystem-specific guides under `docs/dev/sections/`.
- Keep this index updated as new tooling or processes are introduced.
