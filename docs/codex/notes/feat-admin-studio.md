# Feat: Admin Studio UI (P0 | Scope: L)

**Status:** Draft – subject to iteration with UX feedback.  
**Purpose:** Deliver a cohesive admin experience with modular navigation, themable layout, and extensibility hooks for feature modules.

## Core Experiences
- Responsive layout with sidebar navigation, top bar status indicators, and workspace canvas.
- Module-driven navigation: manifests contribute menu items, icons, and feature flags.
- Notification centre (toasts + persistent panel) for background tasks and system alerts.
- Search command palette (`⌘K`) to quickly jump to entities, modules, or commands.
- Contextual help drawer linking to docs, tooltips, inline guides.

## Technical Stack
- Twig templates + Tailwind components with Stimulus controllers for interactions.
- Turbo-powered navigation (partial reloads) to keep stateful widgets responsive.
- Global state store (Stimulus values or lightweight Alpine integration) for toasts, modals.
- Accessibility-first design (ARIA labels, keyboard shortcuts, focus management).

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

## Theming
- Base theme defined with CSS variables; allow per-instance overrides (dark mode, brand colours).
- Module-specific styles scoped via utility classes to avoid conflicts.
- Optional theme pack loader for rapid skinning (later module).

## Internationalisation
- All strings in `admin` translation domain; support per-user locale selection.
- Date/time formatting using Intl; numeric formatting respecting locale.

## Implementation Roadmap
1. Scaffold base layout (sidebar, header, breadcrumbs, notifications).
2. Wire navigation builder to module manifest registry with capability checks.
3. Implement shared UI components (tables, detail panels, modals).
4. Add search palette + quick actions.
5. Bake in help drawer and context docs hooks.

## Open Questions
- Should modules be able to register client-side bundles (React/Vue) or keep to Stimulus-only?
- How do we package custom themes for distribution (zip, composer metapackage)?
- Do we need multi-tenant branding (per project) within the same admin instance?
