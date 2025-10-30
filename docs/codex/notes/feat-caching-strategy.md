# Feat: Caching & Performance Strategy (P1 | Scope: M)

**Status:** Draft – final tuning pending real-world benchmarks.  
**Goal:** Define cache layers to keep the CMS responsive on shared hosting while supporting scale-out scenarios.

## Cache Layers
- **HTTP Cache:** Symfony HTTP cache (reverse proxy headers) with configurable TTL per route; optional integration with CDN.
- **Application Cache:** Use `cache.app` (filesystem by default) for expensive computations (resolver results, navigation trees).
- **Doctrine Result Cache:** Enable for snapshot metadata queries; fallback to SQLite if storage limited.
- **Frontend Asset Cache:** AssetMapper hashed filenames + long-lived cache headers; Tailwind compiled CSS referenced via manifest.

## Invalidations
- Snapshot publish clears relevant cache namespaces (snapshot readers, navigation).
- Draft commits flush resolver cache entries for affected entities.
- Admin maintenance module triggers manual cache clear/warmup.

## Configuration
- Environment-specific cache adapters (filesystem dev, Redis prod optional).
- Busy shared hosting: provide toggle to limit cache size & eviction policy.
- Support `.env` overrides for cache TTL per component.

## Tooling
- Commands: `app:cache:prune`, `app:cache:stats`.
- Monitoring hooks for cache hit/miss metrics (hook into Monolog or dedicated table).

## Implementation Steps
1. Define cache namespaces and adapters in `config/packages/cache.yaml`.
2. Integrate cache invalidation in snapshot/draft services.
3. Add HTTP caching headers to API + frontend controllers.
4. Instrument logging to observe cache behaviour.

## Decisions (2025-10-31)
- Redis remains optional; default installs use filesystem-backed Symfony cache with docs outlining how to enable Redis via `.env`.
- Baseline TTLs ship at 5 minutes for public snapshot responses and 1 minute for admin/draft routes, with environment overrides for bespoke tuning.
- Cache tuning stays environment-driven; the admin UI exposes cache stats/clear actions but does not modify adapters or TTLs.
