# Feat: Schema & Template System (P0 | Scope: L)

**Status:** Draft – will evolve alongside content modelling needs.  
**Aim:** Allow administrators to define JSON schemas, attach Twig templates, and drive content rendering/export through structured definitions.

## Components
- **Schema Registry:** Stores JSON Schema documents per project/global scope; versioned with status (draft/active).
- **Field Definitions:** Support data types (string, markdown, rich-text, relation, media, computed) with validation rules and UI metadata (labels, help text, component hint).
- **Template Registry:** Twig templates referencing schema fields; metadata for target channel (web, email, export).
- **Template Packs:** Bundled schema + templates for reuse/import/export.

## Data Model
- `app_schema`
  - `id` (ULID), `slug`, `scope` (`global`, `project:<id>`), `version`, `status` (`draft`, `active`, `archived`), `json_schema`, `metadata` (UI hints), `created_by`, `created_at`.
  - `requires_preview_migration` flag triggers warnings when breaking changes detected.
- `app_template`
  - `id` (ULID), `schema_id`, `channel` (`web`, `email`, `export`), `twig_source`, `checksum`, `is_default`.
  - `preview_fixture` JSON for snapshot preview fallback.
- `app_schema_pack`
  - `id`, `name`, `version`, `manifest_json`, `archive_path` (stored in `var/packs`).

## Fieldset Definition
- Schemas describe entity payloads using JSON Schema draft 2020-12 with custom keywords:
  - `required`: list of mandatory properties.
  - `additionalProperties`: defaults to `false` unless schema explicitly opts in.
  - `default`: persists initial values when entity created.
  - `x-visible`, `x-exportable`, `x-order`, `x-multiple`, `x-branch`, `x-valid-from`, `x-valid-until` drive UI + behaviour flags.
- Meta section:
  ```json
  {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "additionalProperties": false,
    "properties": {
      "headline": {
        "type": "string",
        "minLength": 5,
        "default": "Untitled",
        "x-visible": true,
        "x-exportable": true,
        "x-order": 10
      },
      "sections": {
        "type": "array",
        "items": { "$ref": "#/definitions/section" },
        "x-multiple": true,
        "x-branch": true
      }
    },
    "definitions": {
      "section": {
        "type": "object",
        "required": ["layout", "content"],
        "properties": {
          "layout": { "type": "string", "enum": ["hero", "grid", "quote"] },
          "content": { "type": "string" }
        }
      }
    }
  }
  ```
- Entity-level defaults (navigation visibility, frontend availability, export inclusion) live in `schema.metadata.entityDefaults` so schema authors influence behaviours without mutating core entity columns.
- Forbidden properties: schema editor will block attempts to override system fields (`title`, `slug`, `author`, `editor`, timestamps, hashes, parent references) to avoid data drift.

## Pack Activation & Project Types
- Packs declare compatible project types:
  ```json
  {
    "projects": [
      { "type": "blog", "schemas": ["blog_post", "blog_category"] }
    ]
  }
  ```
- Activating a pack:
  1. Admin selects target project(s) → wizard lists schemas/templates to install.
  2. System seeds schemas with version `1.0.0` and `draft` status, plus templates + entity defaults.
  3. Project type stored in project metadata; subsequent pack updates offer migrations if compatible.
- Packs can be toggled per project; deactivation keeps schemas but marks them as detached for manual cleanup.

## Versioning Workflow
1. Creating a schema spawns version `1.0.0` with `draft` status.
2. Publishing clones current JSON into immutable history table `app_schema_version`.
3. Breaking-change detector compares new definition → lists required migration steps (fields removed, type changes).
4. Rollbacks via selecting prior version; system regenerates `draft` from stored history.
5. When schema shape must change but existing entities stay valid, editors can bump semantic suffix (`blog_post_v2`), while UI guides through migrating entities gradually.

## Authoring Flow
1. Create schema via builder (JSON editor or form-based UI).
2. Assign schema to entity types; specify allowed children, relation rules.
3. Link templates to schemas; preview rendering with sample data.
4. Publishing a schema triggers validation against existing content; warn about breaking changes.

### Authoring UI Details
- JSON editor provides autocomplete for standard keywords (`type`, `enum`, `pattern`) plus custom `ui:component`.
- Visual form builder persists to same JSON definition, enabling toggling between code and form.
- Preview panel runs validation on sample payload; errors highlight fields inline.

## Rendering Integration
- Twig runtime extensions provide helpers (`schema_field(entity, 'title')`, `schema_iterate(...)`).
- Templates resolve through snapshot data to ensure deterministic output.
- Modules can contribute template filters/functions via manifest.
- If schema lacks bundled Twig template, system falls back to generic renderer that iterates visible fields tabularly, ensuring frontend output never breaks.

## Validation
- Use `justinrainbow/json-schema` for server-side validation.
- Pre-commit validation ensures payload matches active schema version.
- Provide migration assistance: detect incompatible changes, enable diff view.

## Import/Export
- Schema/Template pack format (`.aavpack` JSON + Twig files zipped).
- Installer seeds default pack (blog/docs).
- Command-line utilities: `app:schema:export`, `app:schema:import`.
- Admin UI allows uploading `.aavpack` directly; progress indicator extracts archive and registers pack.
- Pack manifest example:
  ```json
  {
    "name": "Blog Starter",
    "version": "1.0.0",
    "schemas": ["blog_post", "blog_category"],
    "templates": ["blog_post:web", "blog_post:jsonld"],
    "dependencies": {
      "modules": ["navigation"],
      "schemaVersion": ">=1.0.0"
    }
  }
  ```

## Implementation Stages
1. Persistence models (`app_schema`, `app_template`), versioning strategy.
2. Validation service + integration in draft commit pipeline.
3. Admin UI for schema builder (initially JSON editor + docs).
4. Template preview system (render sandbox with sanitized data).
5. Pack import/export + pack registry view.

## Twig Sandbox Rules
- Allowed tags: `if`, `for`, `set`, `include`, `embed`.
- Allowed filters: `escape`, `trans`, `lower`, `upper`, `date`, `json_encode`.
- Forbidden functions: `dump`, `source`, any PHP extension functions.
- Sandbox context injects helper functions `schema_field`, `schema_children`, `asset` (restricted to theme-provided assets).

## Decisions (2025-10-31)
- Initial release skips schema inheritance/composition; revisit after baseline authoring flow stabilises.
- Third-party packs can be delivered either as Composer packages exposing pack paths or as admin-uploaded `.aavpack` archives that feed the same registrar.
- Templates render inside a locked-down Twig sandbox profile to guard against heavy or unsafe logic while preserving required tags and filters.
