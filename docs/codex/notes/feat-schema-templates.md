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

## Decisions (2025-10-31)
- Initial release skips schema inheritance/composition; revisit after baseline authoring flow stabilises.
- Third-party packs can be delivered either as Composer packages exposing pack paths or as admin-uploaded `.aavpack` archives that feed the same registrar.
- Templates render inside a locked-down Twig sandbox profile to guard against heavy or unsafe logic while preserving required tags and filters.
