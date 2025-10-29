# Module: Relation Manager (P1 | Scope: M)

**Status:** Draft – pending refinement alongside hierarchy implementation.  
**Intent:** Provide tooling to manage entity hierarchy (materialized paths), reorder siblings, and manage relation-type entities.

## Feature Highlights
- Admin interface `/admin/relations` with tree view of project hierarchy.
- Drag-and-drop ordering; batch move operations with validation (prevent moving parent into child).
- Relation editor for creating typed links (e.g., cross-project references, navigation).
- Bulk actions: lock/unlock, visibility toggles, menu flags.

## Architecture
- Module manifest registers tree services, routes, Stimulus controller for drag-and-drop.
- `HierarchyService` encapsulates materialized path operations (reindex, move subtree).
- `RelationService` manages entities of type `relation` (source, target, relation_type).
- Use Symfony form components for relation editing with auto-complete (AJAX search).

## Data & Validation
- Materialized path column `path` + `depth` maintained via triggers or service logic.
- Moves executed in transaction with `path` recalculation and version updates.
- Validation ensures constraints: no deletion of parent with active children, no cycles.

## UI/UX
- Left panel tree for selection; right panel details/edit form.
- Inline indicators (draft pending, locked, exportable).
- Context menu for quick actions (create child, clone, move to project).

## Integration
- Works closely with Draft & Commit workflow (changes create drafts automatically when needed).
- Exposes events `HierarchyChangedEvent`, `RelationCreatedEvent`.
- Security: require `ROLE_CONTENT_ARCHITECT` or similar elevated role.

## Implementation Roadmap
1. Build hierarchy service + repository methods for path updates.
2. Create API endpoints for tree fetch and reorder operations (JSON).
3. Develop Stimulus controller for drag/drop and context actions.
4. Integrate with commit workflow to ensure version history reflects changes.

## Considerations
- Performance: limit tree depth fetch; cache common paths.
- Concurrency: use optimistic locking or DB constraints to avoid conflicting moves.
- Future: allow scheduled moves or multi-project linking with approvals.
