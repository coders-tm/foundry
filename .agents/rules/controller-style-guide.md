---
trigger: glob
description: Guidelines for API response structure and messaging in Controllers
globs: src/Http/Controllers/*.php
---

# Controller Style Guide

Follow these guidelines when implementing or modifying Laravel controllers in this package.

## API Response Structure

- **Standard Success Response**: When a response includes both data and a status message, use the following structure:
  ```php
  return response()->json([
      'data' => $resource,
      'message' => __('Entity has been action successfully!'),
  ], 200);
  ```
- **Consistent Keys**: Always use `data` for the primary resource and `message` for the feedback string.

## Messaging & Translations

- **Full String Translation**: Always use the `__` helper with the full string.
  - ✅ `__('Task has been created successfully!')`
  - ❌ `__('task_created')`
- **Standard Phrasing**: Use clear and consistent phrasing for common actions:
  - `[Entity] has been created successfully!`
  - `[Entity] has been updated successfully!`
  - `[Entity] has been deleted successfully!`
  - `[Entity] has been saved successfully!` (for upserts/store)
  - `[Entity] has been synced successfully!`

## Validation

- Prefer form requests or inline validation with clear rules.
- Maintain thin controllers by delegating business logic to Services.

## Comparison

Refer to `src/Http/Controllers/PaymentController.php` as a reference for the expected response structure and messaging style.
