# Feat: Resolver Pipeline (P1 | Scope: L)

**Status:** Draft concept – subject to change during development.  
**Purpose:** Resolve `[ref]` and `[query]` shortcodes deterministically during commit/publish while leaving authoring content intact.

## Responsibilities
- Parse content blocks (Markdown, rich text, JSON fields) for shortcode tokens.
- Resolve references against current draft/active data with cycle detection.
- Produce resolved payloads stored in the published snapshot (original content retains markers).
- Surface validation errors with machine-readable codes and localisation keys.

## Pipeline Stages
1. **Tokenisation:** Twig TokenParser or custom lexer scans fields flagged as resolvable.
2. **Validation:** Ensure referenced entities exist, queries are well-formed, operators supported.
3. **Execution:** Fetch data via repository abstractions; apply filters (`select`, `where`, `sort`, `limit`).
4. **Enrichment:** Embed resolved data (inline string, array, object) and attach meta (source entity, timestamp).
5. **Sanitisation:** Strip resolved data when draft reopens to keep authors editing markers only.

## Services & Components
- `ResolverEngine` orchestrates steps; invoked during `DraftCommittedEvent` and snapshot rebuild.
- `ReferenceResolver` handles `[ref …]`: Follows relations, returns canonical slug/title/URL tuple.
- `QueryResolver` executes filtered lookups; configurable mode (single object vs array).
- `CycleGuard` tracks visited entity/version pairs to avoid infinite loops.
- `ResolverLogger` persists warnings to `app_log` with actionable messages.

## Configuration
- Resolvable fields defined in schema YAML/JSON (`resolvable: true`, `resolver: query|ref`).
- Feature flags allow disabling resolver per project (for debugging).
- Custom operators can be registered via module manifests.

## Error Handling
- Soft failures: replace content with placeholder + inject error metadata for UI to highlight.
- Hard failures: abort commit/publish with summary (e.g., missing required ref).
- All messages translated via `validators` domain.

## Integration Points
- Draft commit service triggers resolver pre-publish; optionally allow "commit with warnings".
- Snapshot writer receives resolved payload for final JSON.
- API/exporters rely on snapshot; no runtime resolving required.

## Implementation Order
1. Token parser + schema flag wiring.
2. Reference resolver (+ tests around self/reference loops).
3. Query resolver with filter parser.
4. Cycle guard + error reporting.
5. Module extension points (custom resolvers).

## Considerations
- Performance: cached lookups per commit to minimise DB hits.
- Security: ensure resolver sanitises user-controlled input (no SQL injection, safe parameter binding).
- Future: allow resolver previews in editor (AJAX call).
