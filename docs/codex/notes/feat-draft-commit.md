# Feat: Draft & Commit Workflow (P0 | Scope: L)

**Status:** Draft blueprint – to be refined during implementation.  
**Goal:** Provide a deterministic content authoring pipeline: drafts, autosave, commit with optional diff, and version activation.

## Functional Breakdown
- Draft creation: duplicate latest active `EntityVersion` into `Draft`.
- Autosave: periodic PATCH to store partial changes; maintain `updated_at` + `updated_by`.
- Commit: promote draft to new `EntityVersion`, mark previous version inactive, optionally capture commit message and change summary.
- Locking: prevent concurrent edits via optimistic locking (version column) + advisory lock using Symfony Lock.
- UI: Editor module (CodeMirror + JSON schema form) with state indicators (draft saved, needs commit).

## API & Services
- `DraftManager` service:
  - `createDraft(Entity $entity, User $user)`
  - `updateDraft(Draft $draft, array $payload)`
  - `commitDraft(Draft $draft, CommitOptions $options)`
- Validation handled via JSON schema registry before commit.
- Event dispatch (`DraftCommittedEvent`) for downstream tasks (resolver, snapshot).

## Data Requirements
- Tables: `app_draft`, `app_entity_version`, `app_entity`.
- Fields:
  - Draft: ULID `id`, FK `entity_id`, `payload_json`, `updated_by`, `updated_at`, `autosave` (bool).
  - Version: ULID `version_id`, FK `entity_id`, `payload_json`, `author_id`, `committed_at`, `commit_message`, `active_flag`.
- Indices: `draft.entity_id` unique (one draft per entity per user), `version.entity_id` for quick history.

## UI Flow
1. Load existing draft (if any) or create new.
2. Editor auto-saves every N seconds or on blur; show toast/banner for status.
3. Commit modal: input message, optional diff preview (hook to diff module later).
4. After commit, refresh active version view + timeline.

## Dependencies
- Requires core platform (module loader, schema validator).
- Triggers: resolver pipeline, snapshot pipeline.
- Exposes hooks for modules (e.g., Diff View to display comparisons in commit modal).

## Implementation Sequence
1. Draft entity + repository, JSON validation, autosave endpoints.
2. Commit service with transaction; ensure active version switch is atomic.
3. UI integration within Admin Studio layout.
4. Events/tests to confirm concurrency rules.

## Known Considerations
- Concurrency: adopt row-level locking or compare `updated_at` tokens on commit to prevent stale overwrite.
- Audit trail: store minimal diffs for future diff module (optional at MVP).
- Large payloads: consider compression or streaming if payloads exceed threshold (deferred).
