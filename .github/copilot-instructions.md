# GitHub Copilot Instructions

## Project Overview

This is `coderstm/foundry`, a comprehensive Laravel package providing essential functionalities including subscription management, multi-auth, theming, notifications, and payment integrations. It's designed as a complete business solution package with extensive service provider architecture.

> **Note**: Detailed, area-specific instructions are available in `.github/instructions/` for different parts of the codebase. These instructions complement this overview.

## Quick Start for New Contributors

### First-Time Setup

```bash
# 1. Install dependencies
composer install

# 2. Build workbench (creates test Laravel app)
vendor/bin/testbench workbench:build

# 3. Run tests to verify setup
composer test

# 4. Start development server (optional)
composer run serve
```

### Verify Your Setup

After setup, you should be able to:

- ✅ Run `composer test` successfully
- ✅ Run `composer lint` without errors
- ✅ Access workbench app at http://localhost:8000 (when using `composer run serve`)

### Common First Tasks

1. **Adding a new feature**: Start by writing a test in `tests/Feature/`
2. **Fixing a bug**: Reproduce it with a test first, then fix
3. **Adding a model**: Create model → migration → factory → tests
4. **Adding an API endpoint**: Controller → Service → Resource → Tests

## Quick Navigation Strategy

1. **Use semantic search** to find intent-level code (e.g., "subscription renewal", "theme activation")
2. **Focus on key directories**: `src/Services` (business logic), `src/Models` (domain), `src/Http/Controllers` (orchestration), `src/Commands` (CLI)
3. **Understand flow before editing**: Open adjacent files (policies, events, listeners) to grasp complete workflows
4. **Start at service providers**: `src/Providers/` shows how the package registers itself and integrates with Laravel

## Development Workflows

### Testing with Testbench

```bash
# Build workbench (required before tests)
vendor/bin/testbench workbench:build

# Run package tests
vendor/bin/testbench package:test

# Start development server
composer run serve
```

### Key Commands

- `foundry:install` - Package installation and provider registration
- `foundry:subscriptions-expired` - Subscription lifecycle management

### Testing Commands

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Feature/SubscriptionTest.php

# Run linting
composer lint

# Build workbench (if needed)
vendor/bin/testbench workbench:build

# Start dev server
composer run serve
```

## Troubleshooting Common Issues

### Tests Failing After Fresh Clone

**Problem**: Tests fail with database errors or missing classes.
**Solution**:

```bash
# Always build workbench first
vendor/bin/testbench workbench:build
composer test
```
