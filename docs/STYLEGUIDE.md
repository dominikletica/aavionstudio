# Documentation Style Guide

> **Purpose:** Provide consistent writing and formatting standards for all documentation (developer, user, codex notes).

## Language & Tone
- Write in English, concise but descriptive.
- Use second-person for user guides (“You can…”), neutral tone for developer docs.
- Avoid colloquialisms; prefer clear, actionable language.

## Structure
- Each document starts with an H1 title (`# Title`), optionally followed by status metadata (`Status: Draft`).
- Use sentence case for headings (except product names): `## Getting started`, `### Error handling`.
- Include a short intro paragraph before diving into detail.
- Sections should follow a logical order: background → prerequisites → steps → references.

## Formatting
- Use markdown lists for steps (`1.`) and unordered bullets (`-`).
- Highlight commands with fenced code blocks (add language hint):
  ```bash
  php bin/console tailwind:build
  ```
- Inline code uses backticks (`\``).
- Tables should have headers and alignment pipes.
- Block quotes (`>`) for callouts or notes; prefix important warnings with **Note**, **Warning**, or **Tip**.

## Naming & Files
- Developer docs: snake-case file names (`schema-validation.md`); user docs: hyphenated names (`content-authoring.md`).
- Feature drafts under `docs/codex/notes/` use prefixes (`feat-`, `module-`).
- Assets (screenshots) go under `docs/assets/` with descriptive names (`admin-dashboard_v1.png`).

## Status Tags
- Optionally include metadata near the top:
  ```
  Status: Draft
  Updated: 2025-10-29
  Owner: Team/Core
  ```
- Update the `Updated` field when substantial changes occur.

## Cross-Referencing
- Link to other docs via relative paths (`[Developer Manual](../dev/MANUAL.md)`).
- When referencing sections, use explicit anchors (`[See draft workflow](content/draft-workflow.md#commit)`).
- Update `docs/codex/WORKLOG.md` when documentation changes require follow-up.

## Screenshots & Media
- Store images under `docs/assets/`; include captions or context in the surrounding text.
- When referencing UI elements, describe the path (e.g., “Go to **Admin → Navigation Builder**”).
- Ensure screenshots show English UI unless documenting localisation.

## Templates
- Use templates in `docs/templates/` when creating new guides:
  - `docs/templates/user-guide-template.md`
  - `docs/templates/dev-guide-template.md`
- Document-specific instructions (e.g., module docs) should inherit structure from these templates.

## Review Checklist
- Confirm table of contents (if present) matches headings.
- Verify links and image references.
- Ensure code samples are tested or clearly marked pseudocode.
- Keep docs aligned with current behaviour; log drift if immediate update is not possible.

Following this guide keeps the documentation clear, predictable, and easy to maintain—especially when multiple agents contribute in parallel.
