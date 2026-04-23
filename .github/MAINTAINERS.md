# Maintainer Guidelines

This document provides guidelines for project maintainers and reviewers.

## Reviewer Responsibilities

### Code Review Process

1. **Check Completeness**
   - Does the PR address the issue/feature completely?
   - Are all related files updated (tests, docs, changelog)?
   - Is the implementation approach reasonable?

2. **Test Coverage**
   - Run tests locally: `composer test`
   - Verify new tests are added
   - Check code coverage for new code (aim for >80%)

3. **Code Quality**
   - Run linting: `composer lint`
   - Check for obvious bugs or security issues
   - Verify type hints are used
   - Look for performance concerns

4. **Documentation**
   - Are comments clear and helpful?
   - Is public API documented?
   - Is CHANGELOG.md updated?
   - Are there breaking changes that need documentation?

5. **Design Review**
   - Does the solution align with project architecture?
   - Are there better alternatives?
   - Is the API design consistent with existing code?
   - Are we introducing unnecessary complexity?

### Review Template

```markdown
## Code Review

✅ **Overall**: [Summary of changes]

### Tests
- [ ] Tests pass locally
- [ ] New tests added
- [ ] Coverage is adequate

### Code Quality
- [ ] Follows project standards
- [ ] No obvious bugs
- [ ] Type hints used
- [ ] Comments are clear

### Documentation
- [ ] CHANGELOG.md updated
- [ ] Comments added for complex logic
- [ ] API documentation updated

### Architecture
- [ ] Approach is reasonable
- [ ] Aligns with project design
- [ ] No unnecessary complexity

### Suggestions
[Optional: Constructive suggestions]

**Status**: Ready to merge / Request changes / Needs more info
```

## PR Merge Process

### Before Merging

1. ✅ Ensure all tests pass
2. ✅ Ensure code style passes (`composer lint`)
3. ✅ At least one approval from maintainer
4. ✅ No merge conflicts
5. ✅ CHANGELOG.md updated
6. ✅ Conventional commit message

### Merging

1. Squash commits if needed for clarity
2. Ensure final commit message follows conventional format
3. Use "Squash and merge" option on GitHub
4. Delete branch after merging

### Example Merge Message

```
feat(orders): add support for partial refunds

- Add Refund::createPartial() method
- Validate refund amount against order total
- Update webhook handling for refund events

Closes #456
```

## Release Process

### Preparation (1 week before release)

1. Review all merged PRs since last release
2. Plan release version (follow semantic versioning)
3. Update CHANGELOG.md with all changes
4. Create release branch: `release/v2.1.0`

### Release (Release day)

1. Update version in:
   - `composer.json`
   - `src/Foundry.php` (if version constant exists)

2. Create final commit:
   ```bash
   git commit -m "chore(release): v2.1.0"
   ```

3. Create git tag:
   ```bash
   git tag -a v2.1.0 -m "Release version 2.1.0

   - Added partial refund support
   - Fixed payment webhook race condition
   
   See CHANGELOG.md for full details."
   ```

4. Push to GitHub:
   ```bash
   git push origin release/v2.1.0
   git push origin v2.1.0
   ```

5. Create GitHub Release:
   - Use the git tag
   - Copy CHANGELOG.md section
   - Highlight breaking changes
   - Add contributor credits

### Post-Release

1. Merge release branch back to main
2. Publish to Packagist (if not automatic)
3. Announce in community channels
4. Update documentation if needed

## Issue Triage

### Labeling

Use these labels to organize issues:

| Label | Usage |
|-------|-------|
| `bug` | Confirmed bugs |
| `enhancement` | Feature requests |
| `documentation` | Doc issues |
| `question` | Questions/support |
| `security` | Security vulnerabilities |
| `good first issue` | Good for new contributors |
| `help wanted` | Looking for contributors |
| `blocked` | Blocked by other work |
| `duplicate` | Duplicate of another issue |

### Triage Steps

1. **Acknowledge** — Thank the reporter, ask clarifying questions if needed
2. **Reproduce** — Verify the issue is reproducible
3. **Label** — Apply appropriate labels
4. **Prioritize** — Assess priority and estimate effort
5. **Assign** — Assign to owner or mark as `help wanted`

### Closing Issues

Before closing, ensure:

- Issue is clearly resolved
- Any related issues are linked
- User has been notified
- Relevant PRs are referenced

---

## Communication Standards

### Being Helpful

- Thank contributors for their effort
- Be specific with feedback (show examples)
- Ask questions to understand intent
- Offer suggestions, not demands
- Acknowledge good work

### Example Good Review

```markdown
Thanks for the PR! I like the approach here.

A few suggestions:
1. Can we add a test for the edge case where amount > order total? 
   (See similar test in `tests/Feature/Orders/RefundTest.php:45`)

2. The error message could be more specific:
   ```php
   // Instead of:
   throw new \Exception('Invalid amount');
   
   // Try:
   throw new InvalidRefundAmountException(
       "Refund amount ($amount) cannot exceed order total ({$order->total})"
   );
   ```

3. Small: The comment on line 87 could explain WHY we check this condition.

Otherwise looks great! Let me know if you have questions.
```

### Difficult Conversations

- Use private channels for sensitive topics
- Assume good intent
- Focus on the behavior, not the person
- Suggest alternatives, don't just reject
- Get maintainer consensus on controversial decisions

## Handling Difficult Situations

### Spam or Abuse

1. Close the issue/PR
2. Apply `spam` label (create if needed)
3. Block user if necessary
4. Report to GitHub if severe

### Stale Issues

1. Comment after 2 weeks of inactivity: "Closing due to inactivity. Please reopen if still relevant."
2. Close after 1 week with no response
3. Always allow reopening

### Controversial Changes

1. Discuss in maintainer team first
2. Get consensus on approach
3. Document decision rationale
4. Link to discussion in commit message

---

## Release Schedule

- **Patch releases** (bug fixes): As needed
- **Minor releases** (features): Monthly
- **Major releases** (breaking changes): Every 6-12 months
- **Security releases**: ASAP (can be out-of-band)

---

## Contributor Recognition

Credit contributors in:

1. **Commit messages** — `Co-authored-by: Name <email>`
2. **CHANGELOG.md** — Link to PR/GitHub profile
3. **Release notes** — Special thanks section
4. **Contributors list** — In README (if maintained)

---

## Team Roles

### Maintainers

- Approve and merge PRs
- Make architectural decisions
- Plan releases
- Respond to security reports
- Overall project direction

### Reviewers

- Review code quality
- Verify tests pass
- Suggest improvements
- Can request changes

### Triagers

- Label and organize issues
- Respond to new issues
- Help duplicate detection
- Gather context

---

## Decision Making

### Small Decisions
- Any maintainer can decide
- Document in commit/PR

### Medium Decisions
- Discuss with 1-2 maintainers
- Link to discussion

### Large Decisions
- Full maintainer consensus
- Public discussion in issue (when possible)
- Document rationale

---

## Community Support

### Responding to Questions

1. Be patient and respectful
2. Direct to documentation first
3. Provide clear examples
4. Follow up to ensure they understand

### Burnout Prevention

- It's okay to say "no" to features
- Prioritize your time
- Ask for help when needed
- Take breaks as needed
- Remember it's volunteer work

---

Thank you for maintaining Laravel Core! 🙏
