# AAVION.MEDIA AGENTS

> **Version:** 0.1.0-dev  
> **Status:** Draft / Active Development  
> For the full specification, refer to [`docs/MANUAL.md`](../docs/MANUAL.md).  
> Implementation notes, todos and sessionlogs live in [`.codex/WORKLOG.md`](./WORKLOG.md); release history in [`CHANGELOG.md`](../CHANGELOG.md).

---

## Key Concepts
- Review and maintain `.codex/OUTLINE.md` for concepts and implementation-ideas.

---

## Session Boot Checklist
- Review `.codex/WORKLOG.md` before coding: align the TODO overview with repository reality, add any missing tasks, and open the next session entry in the worklog timeline.
- Scan documentation (`docs/**`) for pending updates; correct discrepancies immediately or flag them in the TODO list when larger follow-up is needed.
- Ensure visible repository text remains English and terminology stays consistent; schedule clean-up work whenever foreign-language fragments appear.
- Reconfirm the operating rules in this guide and cross-check sandbox/approval notes so upcoming commands follow the expected constraints.

---

## Codex Workflow Guidance
- Keep documentation canonical: update relevant docs under `docs/` and this file whenever behaviour changes. Keep user-manuals and developer-documentation aligned (`docs/user/` and `docs/dev/`). Flag drafts explicitly and clear them as soon as implementations land. Before closing a session (or starting the next one), **read every Markdown file end-to-end** so silent drift is caught immediately.
- Maintain [`.codex/WORKLOG.md`](./WORKLOG.md) as the authoritative worklog: promote completed items out of the TODO overview, capture new follow-ups, log every completed step and close out sessions with concise outcomes.
- Cross-check code and documents after every feature or refactor; update docs immediately or log the mismatch (with owner and next steps) in the TODO list.
- Prefer non-throwing flows: wrap operations in `try/catch`, generate log outputs and make errors visible and self-explanatory.
- When adding features, document them in the appropriate partial and note follow-up items (tests, security review, UI integration).
- Repository content must remain **English-only** (code comments, docs, commit messages). Communicate with the maintainer in German if requested, but never commit German text to the repo; translate or rewrite immediately when such fragments appear.
- Treat `docs/dev/classmap.md` as canonical references for classes and callables; update them whenever APIs change so they remain trustworthy lookup tables.
- Keep the root `README.md` as a concise entry point (overview + pointers) and treat `docs/MANUAL.md` + sub manuals as the canonical documentation set—always update both when behaviour changes.
- Until the first public major release (1.0.0), **backwards compatibility is not required**. Prefer clean refactors over legacy shims; remove obsolete code and update callers/documents immediately.

---

## References
- Developer Manual: [`docs/dev/MANUAL.md`](../docs/dev/MANUAL.md)
- README / user guide: [`README.md`](../README.md)
- Release history: [`CHANGELOG.md`](../CHANGELOG.md)
- Implementation notes and worklog: [`.codex/WORKLOG.md`](./WORKLOG.md)
- Environment recap: [`.codex/ENVIRONMENT.md`](./ENVIRONMENT.md) (active interpreter paths, sandbox rules, helper commands)
- Reusable scripts/snippets: drop them under [`.codex/toolbox/`](./toolbox/) when you create utilities worth keeping
