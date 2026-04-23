# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Documentation audit and refresh for all domains
- New specialist skill rules for Orders, Payments, Wallet, Coupons, Support Tickets, Blog, and Settings
- `.agents/skills/` directory with agent-facing skill files
- Security policy and contribution guidelines

### Changed
- Updated SKILL.md frontmatter to reflect actual package features (removed Shop module references)
- Updated project structure documentation (removed non-existent directories)
- Updated activation keywords in core.blade.php guidelines

### Fixed
- Removed stale "Shop facade" references from documentation
- Fixed broken `.cursor/rules` path to `.agents/rules`
- Corrected controller reference from non-existent TaskController to PaymentController

### Removed
- Removed Shop, Cart, Checkout references from skill documentation

---

## [2.0.0] - 2026-04-23

### Added
- Multi-currency support with dynamic exchange rates
- Order management system with line items, taxes, and discounts
- Payment webhook handling and idempotency
- Wallet and promotional credit system
- Support ticket management with status tracking
- Enhanced RBAC with permission groups
- Blog module with SEO optimization
- Dynamic settings/configuration system

### Changed
- Refactored authentication to support multiple guards
- Updated subscription lifecycle management
- Improved payment processor abstraction

### Fixed
- Security improvements for webhook validation
- Database query optimization for large datasets
- Memory usage optimization for batch exports

### Deprecated
- Legacy Shop module (use Order module instead)
- Deprecated Cart model (use Order module)

### Security
- Fixed SQL injection vulnerability in report generation
- Enhanced webhook signature validation
- Improved CSRF token handling

---

## [1.5.0] - 2025-10-15

### Added
- Support for Stripe webhook events
- Additional payment processors (Razorpay, GoCardless)
- Subscription feature usage tracking
- Basic reporting API

### Fixed
- Bug in subscription renewal calculation
- Issue with multiple concurrent payments

---

## [1.4.0] - 2025-07-22

### Added
- Admin dashboard scaffolding
- Notification template system
- Multi-language support

---

## [1.3.0] - 2025-04-10

### Added
- Initial payment processor implementations
- Subscription management core

---

## [1.2.0] - 2025-01-05

### Added
- Multi-guard authentication
- Permission and module management

---

## [1.1.0] - 2024-10-20

### Added
- Database migrations and factories
- Service provider and package registration

---

## [1.0.0] - 2024-08-15

### Added
- Initial release
- Core authentication system
- Basic model structure

---

## Guidelines for Updating Changelog

### Format

Each release should have a section with the following subsections (as applicable):

- **Added** — New features
- **Changed** — Changes in existing functionality
- **Deprecated** — Features that will be removed in a future release
- **Removed** — Features that have been removed
- **Fixed** — Bug fixes
- **Security** — Security vulnerability fixes

### Naming Conventions

- Use past tense verbs ("Added", not "Adds")
- Group changes by domain when possible
- Link to related issues/PRs: `[#123](https://github.com/coders-tm/foundry/pull/123)`
- Keep entries concise but descriptive

### Example Entry

```markdown
### Added
- [#456](https://github.com/coders-tm/foundry/pull/456) Support for partial order refunds
- New `Refund::createPartial()` method for flexible refund amounts
- Validation to prevent refunding more than order total

### Fixed
- [#789](https://github.com/coders-tm/foundry/issues/789) Race condition in payment processing
```

### Versioning

This project follows **Semantic Versioning**:

- **MAJOR** — Incompatible API changes (breaking changes)
- **MINOR** — New functionality (backwards compatible)
- **PATCH** — Bug fixes (backwards compatible)

Examples:
- `1.0.0` → `2.0.0` — Breaking changes (major refactor)
- `1.0.0` → `1.1.0` — New feature (backwards compatible)
- `1.0.0` → `1.0.1` — Bug fix (backwards compatible)

### When to Update

- Update on every commit that should be user-facing
- Don't update for internal refactors or documentation-only changes
- Add to "Unreleased" section during development
- Move entries to version section when releasing

---

## Release Process

When cutting a new release:

1. Ensure all tests pass
2. Update version in `composer.json`
3. Move "Unreleased" section to new version in CHANGELOG.md
4. Commit with message: `chore: release v2.1.0`
5. Create git tag: `git tag v2.1.0`
6. Push tag and commits
7. Create GitHub release from the git tag

Example release commit:

```
chore: release v2.1.0

- Added partial refund support
- Fixed payment webhook race condition
- Updated documentation

See CHANGELOG.md for full details.
```
