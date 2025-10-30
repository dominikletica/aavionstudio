# Feat: Admin Studio UI (P0 | Scope: L)

**Status:** Draft – subject to iteration with UX feedback.  
**Purpose:** Deliver a cohesive admin experience with modular navigation, themable layout, and extensibility hooks for feature modules.

## Core Experiences
- Responsive layout with sidebar navigation, top bar status indicators, and workspace canvas.
- Module-driven navigation: manifests contribute menu items, icons, and feature flags.
- Notification centre (toasts + persistent panel) for background tasks and system alerts.
- Search command palette (`⌘K`) to quickly jump to entities, modules, or commands.
- Contextual help drawer linking to docs, tooltips, inline guides.

## Layout Structure
- **Sidebar:** grouped navigation with collapsible sections, pinned favourites, project switcher.
- **Top Bar:** breadcrumb trail, global search, profile menu (language, theme toggle, logout).
- **Content Canvas:** Turbo frames for CRUD views, detail panels, modals.
- **Notification Drawer:** streaming updates from Messenger queue (push via Mercure/SSE).

## Technical Stack
- Twig templates + Tailwind components with Stimulus controllers for interactions.
- Turbo-powered navigation (partial reloads) to keep stateful widgets responsive.
- Global state store (Stimulus values or lightweight Alpine integration) for toasts, modals.
- Accessibility-first design (ARIA labels, keyboard shortcuts, focus management).

### Component Library
- `ui-button`, `ui-card`, `ui-table`, `ui-tabs` Twig components with BEM-inspired class names.
- Shared Stimulus controllers: `modal_controller`, `dropdown_controller`, `clipboard_controller`.
- Design tokens defined in CSS variables (`--color-primary`, `--spacing-base`); theme packs override via admin UI.

## Module Integration
- Navigation manifest structure:
  ```yaml
  navigation:
    - label: "Content"
      icon: "content"
      route: "aavion_admin_content"
      capability: "content.view"
      children:
        - label: "Drafts"
          route: "aavion_admin_drafts"
```
- Modules can inject dashboards cards, actions, or tabs via named Twig blocks.
- Provide UI kit components (buttons, cards, tables) as reusable Twig macros/Stimulus wrappers.

### Command Palette
- Trigger via `⌘K` or `/`.
- Data sources: routes registered with `searchable: true`, recent entities, admin actions (`Clear cache`, `New draft`).
- Palette results include keyboard hints; selecting executes Turbo visit or opens modal.

## Theming
- Base theme defined with CSS variables; allow per-instance overrides (dark mode, brand colours).
- Module-specific styles scoped via utility classes to avoid conflicts.
- Optional theme pack loader for rapid skinning (later module).

### Theme Management UI
1. `Themes` index lists installed packs with preview thumbnails, version, compatibility badges.
2. Upload form accepts `.aavtheme` zip; backend validates `theme.yaml`, extracts into `var/themes/<slug>`.
3. Activation toggles update project settings and triggers asset map rebuild (cached when precompiled).
4. Live preview uses `/admin?theme=<slug>` query param stored per session.
5. Theme cards surface repository metadata (`repository`, `latestVersion`) pulled from packaged manifest to aid manual updates without shell access.

## Internationalisation
- All strings in `admin` translation domain; support per-user locale selection.
- Date/time formatting using Intl; numeric formatting respecting locale.

## Contextual Help
- Each primary view integrates `HelpDrawer` component with markdown-driven guidance.
- Drawer fetches from `docs/dev/*.md` or module-supplied guides; caching ensures offline availability.
- Inline tooltips triggered via `data-help-target`.

## Implementation Roadmap
1. Scaffold base layout (sidebar, header, breadcrumbs, notifications).
2. Wire navigation builder to module manifest registry with capability checks.
3. Implement shared UI components (tables, detail panels, modals).
4. Add search palette + quick actions.
5. Bake in help drawer and context docs hooks.

## Decisions (2025-10-31)
- Extensions stick to Stimulus-compatible ES modules; revisit heavier frameworks only if a first-party module justifies the cost.
- Theme distribution standardises on zipped packs (`.aavtheme`) or Composer packages exposing `theme.yaml` manifests so operators can install via the admin UI.
- Support instance-level branding plus per-project accent settings (logo/colour); full multi-tenant theming remains out of scope for launch.
