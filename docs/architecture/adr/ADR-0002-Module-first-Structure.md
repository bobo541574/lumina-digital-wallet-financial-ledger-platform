# ADR-0002 — Module-first directory structure (no REST resource folders)

Status: Accepted (2026-10-10)
Context: BLUEPRINT requires modules; cross-cutting folders (Controllers, Models) do not
convey ownership for a money movement platform.
Decision: Code lives in `app/Modules/<Module>/` with internal
`Http/Controllers`, `Models`, `Services`, `Enums`, `Events`, etc. Domain logic wins over
framework-default layout. `app/Http` is a thin entry point only where required by Laravel.
Consequences: Naming must mirror Laravel conventions inside each module so Artisan and
`route:list` behave normally; a new base folder outside the existing structure requires
approval (AGENTS.md).
Enforced by: `.ai/rules` path-scoped rules; `StructureTest` in suite.
References: During build per BLUEPRINT ADR-0002.