# Notification & Templates Rules

The package uses a dynamic database-driven template system for system notifications.

## Template Rendering
- **Service**: `Foundry\Services\NotificationTemplateRenderer`
- Templates are stored in the `templates` table and rendered using Mustache or Blade syntax (depending on config).
- **Model**: `Foundry\Models\Template`.

## Repository
- Default stubs are located in `stubs/database/templates`.
- When adding a new notification, add the corresponding stub and seed it via `TemplateSeeder`.

## Standard Channels
- **Mail**: Standard Laravel Mailables that fetch content from the template renderer.
- **Database**: Notifications persisted for in-app viewing.

## Naming Conventions
- Templates follow the pattern `{context}:{action}` (e.g., `admin:new-user-signup`, `user:subscription-renewed`).

## Best Practices
- Never hardcode notification strings in controllers.
- Use the `Template` model to allow administrators to edit content via the admin panel.
- Ensure all variable placeholders in templates are provided in the `data` array during rendering.
