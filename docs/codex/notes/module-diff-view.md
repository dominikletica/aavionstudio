# Module: Diff View (P2 | Scope: M)

**Status:** Draft – implementation window after core workflow stabilises.  
**Goal:** Provide visual diffs between entity versions to aid review before publish.

## Feature Set
- Admin route `/admin/diff/<entity>/<version>` accessible from history view and commit modal.
- Supports JSON diff (structured) and rendered Markdown diff.
- Highlights resolver output changes vs raw content to clarify publish impact.
- Optional side-by-side and inline diff modes.

## Architecture
- Module manifest registers:
  - Services for diff calculation (`DiffEngine`, `JsonDiffFormatter`, `MarkdownDiffFormatter`).
  - Routes under `modules/diff-view/config/routes.yaml`.
  - Admin navigation integration (submenu item `Diff Viewer`, priority 400).
- Uses `sebastian/diff` or custom diff engine for textual comparisons.
- Stimulus controller for toggling diff modes and filters.

## Data Flow
1. User selects two versions (e.g., current draft vs latest active).
2. Controller fetches payloads via `EntityVersionRepository`.
3. Diff engine produces structured output (added, removed, changed nodes).
4. Twig template renders diff with collapsible sections and metadata.

## Dependencies
- Relies on Draft & Version history feature for content snapshots.
- Optional integration with Resolver pipeline to preview resolved outputs.
- Security: restricted to admins with version-management permissions.

## Implementation Notes
- Provide API endpoint for diff JSON (to reuse in commit modal).
- Cache recently computed diffs in `cache.app` to avoid recalculation.
- Ensure large payloads are truncated or chunked for performance.

## Future Enhancements
- Inline comments/annotations.
- Export diff report (PDF/HTML) for audit.
- Integration with notifications (email summarising changes before publish).
