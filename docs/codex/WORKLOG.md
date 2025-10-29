# Developer Notes

> Maintainer: Codex (GPT-5)  
> Purpose: Track implementation decisions, open questions, and follow-up tasks during development.

## TODO
### Core Platform
- [ ] Example

### Example Feature
- [ ] Example

## Roadmap To Next Release
- [ ] **Step 1:** Example

## Planned Implementations, Outlined Ideas
> Concept drafts live in `docs/codex/notes/*.md`

- [Project Outline](./notes/OUTLINE.md)

## Session Logs
### 2025-10-29
- Initialised Symfony skeleton in repository and verified clean install
- Added Tailwind Bundle, importmap assets, CodeMirror integration, and Stimulus controller scaffold
- Extended composer requirements (Flysystem, UID, JSON schema validator, GeoIP, Rate Limiter, Messenger, PHP extensions)
- Configured Doctrine for dual SQLite databases with attach listener, + ULID/UUID types
- Updated `.env` defaults for SQLite, configured messenger/lock cache, confirmed runtime via `php bin/console about`
- Created contributor guide (`AGENTS.md`) and aligned docs under `docs/codex/**`
- Translated and updated project outline to English with deployment strategy revisions
