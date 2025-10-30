# aavion Studio

> **Status:** Active development (prototype) – :warning: not production-ready  
> **TL;DR:** Modular Symfony CMS with schema-driven content, Git-like versioning, deterministic snapshots, and LLM-friendly exports.

## Overview
- **Owner:** Dominik Letica · dominik@aavion.media  
- **Tech Stack:** PHP 8.2+, Symfony 7.3, SQLite (dual files), Twig, Tailwind, Stimulus, Importmap  
- **Key Concepts:** Draft → Commit workflow, shortcode resolver pipeline, snapshot-based delivery, optional module system (exporter, navigation, theming, maintenance, etc.)
- **Roadmap:** See [`docs/codex/WORKLOG.md`](docs/codex/WORKLOG.md) for live TODOs and session logs.

## Documentation
- **Developer Manual:** [`docs/dev/MANUAL.md`](docs/dev/MANUAL.md)  
  Architecture, setup scripts, class map, and subsystem guides.
- **User Manual:** [`docs/user/MANUAL.md`](docs/user/MANUAL.md)  
  Installation, content authoring, publishing, and administration.
- **Concept Outline:** [`docs/codex/notes/OUTLINE.md`](docs/codex/notes/OUTLINE.md)  
  High-level vision, non-functional goals, distribution strategy, and roadmap.
- **Feature Drafts:** Browse `docs/codex/notes/` for detailed plans (core platform, API, modules, etc.).

## Quick Start
```bash
git clone https://github.com/dominikletica/aavionstudio.git
cd aavionstudio
bin/init dev
symfony serve # or php -S localhost:8000 -t public
```

The init script installs Composer dependencies, refreshes importmap assets, builds Tailwind CSS, prepares SQLite databases, ensures Messenger transports, warms caches, and writes `.env.local` for the chosen environment. Rerun `bin/init prod` (or `bin/init test`) whenever you need to switch contexts.

## Contribution Guidelines
- External contributions are not yet open. Please use Issues to share feedback or questions.
- Coding standards: PSR-12, Tailwind utility-first CSS, Stimulus controllers for interactivity.
- Documentation must remain English. Update relevant manuals whenever behaviour changes.

## Licensing
Creative Commons BY-SA 4.0 – refer to [`LICENSE`](LICENSE) for details.
