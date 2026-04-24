# RBAC & Permissions Rules

The Role-Based Access Control (RBAC) system manages cross-domain permissions.

## Core Structure
- **Module**: High-level feature area (e.g., `Reports`, `Orders`, `Blogs`).
- **Permission**: Specific action within a module (e.g., `view`, `create`, `update`, `delete`).
- **Group**: Role that bundles multiple permissions.

## Traits & Models
- **Trait**: `Foundry\Concerns\HasPermission` — Use on Admin model.
- **Model**: `Foundry\Models\Admin\Module`.
- **Model**: `Foundry\Models\Admin\Permission`.
- **Model**: `Foundry\Models\Admin\Group`.

## Checking Permissions
- Use `$admin->hasPermission('module-slug', 'action')`.
- Middleware: `GuardMiddleware` automatically handles basic permission checks for admin routes.

## Best Practices
- Always link permissions to a Module.
- Use the `Module` model to group UI elements in the admin sidebar.
- Prefer group-based permission assignments over direct per-admin assignments.
