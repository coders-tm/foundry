# Quick Start Guide for Contributors

Welcome to Laravel Core! This quick reference helps you get started quickly.

## 🚀 First Time Setup (5 minutes)

```bash
# 1. Fork on GitHub and clone
git clone https://github.com/YOUR_USERNAME/foundry.git
cd foundry

# 2. Add upstream remote
git remote add upstream https://github.com/coders-tm/foundry.git

# 3. Install dependencies
composer install

# 4. Build workbench (test app)
vendor/bin/testbench workbench:build

# 5. Run tests to verify setup
composer test
```

✅ All tests passing? You're ready!

---

## 📝 Making Your First Contribution

### Option 1: Fix a Bug

1. **Find an issue** — Look for issues labeled `bug` or `good first issue`
2. **Reproduce it** — Understand the problem locally
3. **Create a branch** — `git checkout -b fix/issue-123-brief-title`
4. **Write a test** — Create failing test in `tests/Feature/` or `tests/Unit/`
5. **Fix the bug** — Make the test pass
6. **Commit** — `git commit -m "fix: brief description"`
7. **Push & PR** — Push your branch and create a pull request

### Option 2: Add a Feature

1. **Open an issue first** — Discuss the feature before implementing
2. **Create a branch** — `git checkout -b feature/your-feature`
3. **Write tests first** — TDD approach is great
4. **Implement feature** — Make tests pass
5. **Document** — Add docstrings and update CHANGELOG
6. **Commit & PR** — Follow conventional commits

### Option 3: Improve Documentation

1. **Find typo/error** — In docs, code comments, or README
2. **Create a branch** — `git checkout -b docs/fix-xyz`
3. **Make changes** — Update `.md` files or docstrings
4. **Commit** — `git commit -m "docs: fix typo in SECURITY.md"`
5. **Push & PR** — Create pull request

---

## 📋 Before Pushing Your Code

Run these commands to ensure everything passes:

```bash
# Check code style
composer lint

# Run all tests
composer test

# Verify no errors
composer lint
```

All green? ✅ You're ready to push!

---

## 🔄 Creating a Pull Request

1. **Push your branch**
   ```bash
   git push origin fix/issue-123-brief-title
   ```

2. **Create PR on GitHub** — Click "New Pull Request"

3. **Fill out template** with:
   - Description of changes
   - Related issue (use `Closes #123`)
   - How to test your changes
   - Any breaking changes

4. **Wait for review** — Maintainers will review within 48 hours

5. **Address feedback** — Make requested changes and push again

---

## 🏗️ Project Structure (Quick Reference)

```
src/
├── Models/           # Database models
├── Services/         # Business logic
├── Http/             # API controllers
├── Payment/          # Payment processors
├── Commands/         # CLI commands
└── ...

tests/
├── Feature/          # Integration tests
└── Unit/             # Unit tests

resources/boost/
└── skills/           # AI agent documentation
```

---

## 🧪 Testing Cheatsheet

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Feature/OrderTest.php

# Run tests matching pattern
vendor/bin/phpunit --filter "refund"

# Run with coverage
composer test -- --coverage

# Run a single test method
vendor/bin/phpunit --filter "testRefund"
```

---

## 📚 Important Files

| File | Purpose |
|------|---------|
| `CONTRIBUTING.md` | Full contribution guide |
| `SECURITY.md` | Security policy & best practices |
| `CODE_OF_CONDUCT.md` | Community standards |
| `CHANGELOG.md` | Change log & version history |
| `.github/MAINTAINERS.md` | Maintainer guidelines |
| `README.md` | Project overview |

---

## 🎯 Common Tasks

### Writing a Test

```php
// tests/Feature/Orders/RefundTest.php
public function test_can_refund_order(): void
{
    $order = Order::factory()->create(['total' => 100]);
    
    $refund = $order->refund(50);
    
    $this->assertEquals(50, $refund->amount);
    $this->assertDatabaseHas('refunds', ['order_id' => $order->id]);
}
```

### Writing a Commit Message

```
feat(orders): add partial refund support

Allow users to issue partial refunds on orders instead
of only full refunds.

- Add Refund::createPartial() method
- Validate refund amount against order total
- Update webhook handling

Closes #456
```

### Checking Your Branch

```bash
# See current branch
git branch

# See all branches
git branch -a

# Sync with upstream
git fetch upstream
git rebase upstream/main
```

---

## ❓ Frequently Asked Questions

### Q: How long does review take?
**A:** Usually 24-48 hours. Complex changes may take longer.

### Q: What if I have questions?
**A:** Ask in the PR, open an issue, or check existing documentation.

### Q: Can I work on multiple issues?
**A:** Yes! Just create separate branches for each.

### Q: What if tests are failing?
**A:** Check error messages, run tests locally, review your code.

### Q: Do I need to update CHANGELOG?
**A:** Yes! Add your changes to the "Unreleased" section.

### Q: What if there are merge conflicts?
**A:** 
```bash
git fetch upstream
git rebase upstream/main
# Fix conflicts, then:
git add .
git rebase --continue
git push --force-with-lease
```

---

## 🚨 Things to Avoid

- ❌ Don't commit sensitive data (keys, tokens, credentials)
- ❌ Don't make unrelated changes in one PR
- ❌ Don't skip tests or linting
- ❌ Don't directly push to `main`/`develop`
- ❌ Don't update version numbers (maintainers do that)
- ❌ Don't be discouraged by feedback (it's constructive!)

---

## ✨ Tips for Success

✅ **Start small** — Small PRs are easier to review and merge  
✅ **Ask questions** — No question is too simple  
✅ **Read code** — Learning how things work helps you contribute better  
✅ **Write tests** — They make your code more confident  
✅ **Be patient** — Maintainers are volunteers  
✅ **Celebrate wins** — Every contribution matters!

---

## 📞 Need Help?

- **Documentation**: Check `CONTRIBUTING.md` for full guide
- **Issues**: Ask in GitHub issues
- **Security**: Email security@foundry.com
- **General**: Check project discussions

---

## 🎉 You're All Set!

Ready to make your first contribution? Start with an issue labeled `good first issue`!

Thank you for contributing to Laravel Core! 🚀
