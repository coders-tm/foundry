# Workflow: Adding Boost Support to a Laravel Package

Boost gives AI coding assistants (Claude Code, Cursor, Copilot, Codex) context-aware skills and searchable documentation. There are two artefacts to produce:

| Artefact | Path | Purpose |
|---|---|---|
| **Skill** | `resources/boost/skills/{skill-name}/SKILL.md` | Context-aware activation — tells the AI *when* to load this skill and *how* to behave |
| **Guidelines** | `resources/boost/guidelines/{name}.blade.php` | Core package context — surfaced via the `search-docs` MCP tool |

---

## Step 1 — Create the directory structure

```bash
mkdir -p resources/boost/skills/{package-name}
mkdir -p resources/boost/guidelines
```

Replace `{package-name}` with a kebab-case identifier that matches what developers type (e.g. `page-builder`, `laravel-cashier`, `spatie-media-library`).

---

## Step 2 — Write the Skill file

`resources/boost/skills/{package-name}/SKILL.md`

The skill has two sections: a YAML frontmatter block and a Markdown body.

### Frontmatter (required)

```yaml
---
name: {package-name}
description: >
  One or two sentences describing WHEN this skill should activate.
  Be specific about the file types, keywords, and task patterns that trigger it.
  Example: "Activate when creating or modifying X, Y, Z for the vendor/package package."
---
```

**Tips for a good `description`:**
- List the specific nouns a developer would type: class names, config keys, Artisan commands, Blade directives.
- List the verbs: "creating", "modifying", "debugging", "configuring".
- Name the package explicitly (`vendor/package`) so cross-package AI context never bleeds.

### Body structure

Follow this template exactly — each section maps to a different developer intent:

```markdown
# {Package Display Name} Development

One-paragraph summary of what the package does and its architectural style.

## Documentation

Use `search-docs` for detailed {package name} patterns and documentation.

## Architecture

Describe the top-level structure in a bullet list. Keep it under 10 bullets.
Reference `search-docs` for layer boundaries and DI patterns.

## Usage

- **Key class/facade**: `ClassName::method()` — what it does
- **Config**: `config/package.php` — key options
- **Helpers**: function names and what they return
- **Artisan**: commands the developer runs most often
- **Routes**: route prefixes and purposes

## Workflows

One `### Workflow Name` subsection per common developer task.
Use a checkbox list so the AI can track progress.

### Create a {Thing}

```
- [ ] Step one
- [ ] Step two
- [ ] Step three
```

> Use `search-docs` for detailed patterns.

## Best Practices

Short prose rules — one paragraph per principle.
Cover: strict typing, DI rules, immutability, rendering pipeline, naming conventions.

## Key API Endpoints (if applicable)

| Purpose | Method | Endpoint |
|---|---|---|
| Short description | GET/POST | `/prefix/endpoint` |
```

---

## Step 3 — Write the Guidelines file

`resources/boost/guidelines/{name}.blade.php`

This file is served as plain text/Markdown to the `search-docs` MCP tool. Keep it concise — it is loaded on every relevant AI query.

```blade
# {Package Display Name}

- {One sentence: what the package is and what it does.}
- IMPORTANT: Always use the `search-docs` tool for detailed {package name} patterns and documentation.
- IMPORTANT: Activate `{skill-name}` skill when working with {comma-separated list of key concepts}.
```

**Rules:**
- Maximum 5–6 bullet points.
- Each `IMPORTANT:` line instructs the AI on a mandatory behaviour.
- Mention the exact skill name from the frontmatter `name` field.
- Use `search-docs` references so the AI knows there is deeper documentation available.

---

## Step 4 — Register a route to serve the guidelines (optional but recommended)

If you want the guidelines to be queryable via an MCP `search-docs` tool, expose a route that renders the Blade view as plain text.

Add to your package's `routes/web.php` (or a dedicated boost routes file):

```php
use Illuminate\Support\Facades\Route;

Route::get('boost/guidelines/{name}', function (string $name) {
    $view = "vendor.{package}.boost.guidelines.{$name}";

    abort_unless(view()->exists($view), 404);

    return response(view($view)->render(), 200)
        ->header('Content-Type', 'text/plain; charset=utf-8');
})->name('{package}.boost.guidelines');
```

Publish the boost views so the host application can override them:

```php
// In your ServiceProvider::boot()
$this->publishes([
    __DIR__.'/../../resources/boost' => resource_path('views/vendor/{package}/boost'),
], '{package}-boost');
```

Load the views under a namespaced prefix:

```php
// In your ServiceProvider::boot()
$this->loadViewsFrom(__DIR__.'/../../resources/boost', '{package}-boost');
```

---

## Step 5 — Validate

Run through this checklist before committing:

```
- [ ] resources/boost/skills/{name}/SKILL.md exists with valid YAML frontmatter
- [ ] SKILL.md `name` field matches the kebab-case directory name
- [ ] SKILL.md `description` names specific classes, directives, or task patterns
- [ ] SKILL.md body has: Architecture, Usage, Workflows (with checkbox lists), Best Practices
- [ ] Every workflow section ends with "> Use `search-docs` for detailed patterns."
- [ ] resources/boost/guidelines/{name}.blade.php exists
- [ ] Guidelines file has at least one IMPORTANT: line naming the skill
- [ ] Guidelines file has at least one IMPORTANT: line referencing search-docs
- [ ] Route (if added) returns Content-Type: text/plain
- [ ] Views are published under a namespaced tag
```

---

## Reference: page-builder Boost Files

Use the existing package implementation as the canonical example:

| File | Role |
|---|---|
| [resources/boost/skills/page-builder-development/SKILL.md](../../../resources/boost/skills/page-builder-development/SKILL.md) | Complete skill with all required sections |
| [resources/boost/guidelines/core.blade.php](../../../resources/boost/guidelines/core.blade.php) | Minimal guidelines file with IMPORTANT directives |

---

## Naming Conventions

| Item | Convention | Example |
|---|---|---|
| Skill directory | `kebab-case` matching the skill `name` | `laravel-cashier` |
| Skill `name` in frontmatter | Same as directory | `laravel-cashier` |
| Guidelines filename | `kebab-case.blade.php` | `core.blade.php`, `media-library.blade.php` |
| Route parameter | Matches guidelines filename (without extension) | `core`, `media-library` |
| Publish tag | `{package}-boost` | `pagebuilder-boost` |

---

## Common Mistakes

- **Vague skill description**: "Activate when working with the package" is too broad. Name specific classes, directives, or config keys.
- **Missing `search-docs` references**: Without them the AI won't know to call the tool for deeper context.
- **Guidelines file too long**: Keep it under 10 lines — it is loaded on every query. Deep docs belong in the `.ai/guidelines/` markdown files.
- **Mismatched skill name**: The `name` in frontmatter, the directory name, and the `IMPORTANT: Activate {name}` line in the guidelines must all match exactly.
- **No workflow checklists**: The checkbox lists (`- [ ]`) are how the AI tracks multi-step tasks. If they are missing, the AI skips steps.
