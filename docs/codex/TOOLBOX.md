# Codex Toolbox

Status: Draft  
Updated: 2025-11-01

This page tracks reusable helpers available in `.codex/` so future sessions know which tools already exist.

| Tool | Path | Purpose | Notes |
|------|------|---------|-------|
| Route renderer | `.codex/render.php` | Render any Symfony route/URL from CLI (`php .codex/render.php /setup`) using the full theme → module → default cascade | Accepts optional HTTP method as second CLI argument; also works as a web endpoint (`render.php?route=/setup`). |

## Maintenance

- Add an entry for every new script dropped under `.codex/`.
- Keep descriptions concise but actionable (include usage pattern or constraints).
- Update this file whenever a tool’s interface changes.

