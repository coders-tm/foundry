---
trigger: glob
description: Conventions for PHP/Laravel code in this package to guide navigation and generation
globs: *.php
---

## PHP Style

- **Types**: Use scalar and return types where possible. Prefer explicit over implicit.
- **Naming**: Descriptive, full-word names. Avoid abbreviations. Methods are verbs; classes are nouns.
- **Control flow**: Prefer early returns; avoid deep nesting. Only catch exceptions when meaningful handling is present.
- **Comments**: Keep minimal and high-signal. Document invariants, side-effects, and non-obvious decisions.

## Laravel Conventions

- **Models** live under [`src/Models`](md:src/Models). Relationships should use clear, intention-revealing names.
- **Migrations** are under [`database/migrations`](md:database/migrations); ensure idempotent, reversible operations.
- **Policies** under [`src/Policies`](md:src/Policies) authorize access at controller or model levels.
- **Requests/Validation**: Prefer form requests or validator classes; keep controllers thin.
- **Controllers** in [`src/Http/Controllers`](md:src/Http/Controllers) should orchestrate services rather than contain business logic.
- **Services** in [`src/Services`](md:src/Services) encapsulate domain logic and integrations.
- **Events/Listeners/Jobs** in their respective folders under [`src`](md:src) for decoupled, queued workflows.
- **Resources** in [`src/Http/Resources`](md:src/Http/Resources) shape API responses; avoid leaking internals.

## Testing

- **Structure**: Feature tests under [`tests/Feature`](md:tests/Feature) and unit tests under [`tests/Unit`](md:tests/Unit).
- **Factories**: Centralized in [`database/factories`](md:database/factories) and used across tests.
- **Testbench**: Package is tested via Orchestra Testbench per [`testbench.yaml`](md:testbench.yaml).

## Package-Specific Notes

- The package exposes artisan commands in [`src/Commands`](md:src/Commands) for subscription and theme workflows.
- Translations are in [`resources/lang`](md:resources/lang); keep keys consistent across locales.
- Blade views in [`resources/views`](md:resources/views) should remain presentation-only; keep heavy logic out.
