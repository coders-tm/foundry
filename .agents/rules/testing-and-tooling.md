---
description: How to run tests and common tooling/commands for this Laravel package
---

## Testing

- **PHPUnit**: Configured via [`phpunit.xml.dist`](md:phpunit.xml.dist). Tests live in [`tests/`](md:tests).
- **Orchestra Testbench**: Integration is configured via [`testbench.yaml`](md:testbench.yaml). Base setup in [`tests/BaseTestCase.php`](md:tests/BaseTestCase.php).
- **Factories**: Use models from [`database/factories`](md:database/factories) to generate fixtures.

### Common Commands

```bash
# Build the workbench app (required before running tests)
vendor/bin/testbench workbench:build

# Run the package test suite via Testbench
vendor/bin/testbench package:test

# Or use composer script if available
composer run test
```

## Static Analysis and Quality

- **PHPStan**: Config at [`phpstan.neon.dist`](md:phpstan.neon.dist)
- **Coding style**: Follow project conventions in [php-laravel-conventions.md](md:.agents/rules/php-laravel-conventions.md)

## Build/Assets (Workbench)

- Workbench assets and configuration: [`workbench/`](md:workbench)
- Public assets referenced by demo/workbench live in [`public/`](md:public)

## Package Commands

- Artisan commands are under [`src/Commands`](md:src/Commands) and include subscription maintenance and theme tooling. See class docblocks for usage.

