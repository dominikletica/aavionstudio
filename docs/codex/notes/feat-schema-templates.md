# Feat: Schema & Template System (P0 | Scope: L)

**Status:** Draft – will evolve alongside content modelling needs.  
**Aim:** Allow administrators to define JSON schemas, attach Twig templates, and drive content rendering/export through structured definitions.

## Components
- **Schema Registry:** Stores JSON Schema documents per project/global scope; versioned with status (draft/active).
- **Field Definitions:** Support data types (string, markdown, rich-text, relation, media, computed) with validation rules and UI metadata (labels, help text, component hint).
- **Template Registry:** Twig templates referencing schema fields; metadata for target channel (web, email, export).
- **Template Packs:** Bundled schema + templates for reuse/import/export.

## Authoring Flow
1. Create schema via builder (JSON editor or form-based UI).
2. Assign schema to entity types; specify allowed children, relation rules.
3. Link templates to schemas; preview rendering with sample data.
4. Publishing a schema triggers validation against existing content; warn about breaking changes.

## Rendering Integration
- Twig runtime extensions provide helpers (`schema_field(entity, 'title')`, `schema_iterate(...)`).
- Templates resolve through snapshot data to ensure deterministic output.
- Modules can contribute template filters/functions via manifest.

## Validation
- Use `justinrainbow/json-schema` for server-side validation.
- Pre-commit validation ensures payload matches active schema version.
- Provide migration assistance: detect incompatible changes, enable diff view.

## Import/Export
- Schema/Template pack format (`.aavpack` JSON + Twig files zipped).
- Installer seeds default pack (blog/docs).
- Command-line utilities: `app:schema:export`, `app:schema:import`.

## Implementation Stages
1. Persistence models (`app_schema`, `app_template`), versioning strategy.
2. Validation service + integration in draft commit pipeline.
3. Admin UI for schema builder (initially JSON editor + docs).
4. Template preview system (render sandbox with sanitized data).
5. Pack import/export + pack registry view.

## Open Questions
- Should we support schema inheritance / composition out of the gate?
- How do we allow third-party modules to ship schema packs (Composer package vs manual upload)?
- Do we need runtime template sandboxing to guard against heavy Twig logic?
