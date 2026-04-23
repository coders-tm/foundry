# Blog Domain

## Overview

The Blog domain provides content management for blog posts including slugs, categories, publishing, and SEO metadata.

## Core Models & Services

- **`Blog`** (`src/Models/Blog.php`) — Main blog post model with title, slug, content, author, status, published_at, and metadata.
- **`BlogService`** (`src/Services/BlogService.php`) — Service handling post creation, updates, publishing, and retrieval logic.
- **`Blog` Facade** (`src/Facades/Blog.php`) — Request-scoped accessor for blog operations (e.g., `Blog::publishedPosts()->paginate()`).

## Key Workflows

### Creating a Blog Post

1. Create `Blog` instance with:
   - `title` (post title)
   - `slug` (URL-friendly identifier; auto-generated from title if not provided)
   - `content` (HTML or markdown body)
   - `author_id` (user who authored)
   - `status` (draft, published, archived)
   - `published_at` (null initially, set on publish)
   - Optional: `excerpt` (summary), `featured_image` (hero image), `category`, `tags`, `meta_description`, `meta_keywords`

2. Call `BlogService::create($data)` which handles slug generation, content sanitization, and event dispatch.

### Publishing a Post

1. Create draft post with `status = 'draft'` and `published_at = null`.
2. Call `BlogService::publish($blog)` to:
   - Set `status = 'published'`
   - Set `published_at = now()` if not already set
   - Event `BlogPublished` is dispatched for notifications/indexing

### Retrieving Posts

```php
// Published posts only
$posts = Blog::published()->paginate();

// By slug (for detail page)
$post = Blog::slug('my-post-title')->first();

// By category
$posts = Blog::category('technology')->published()->get();

// Drafts for author dashboard
$drafts = Blog::status('draft')->whereAuthorId($userId)->get();
```

## Database Relations

- `Blog` → `User` (belongsTo) — author relation
- `Blog` → `Category` (belongsTo) — category if applicable
- `Blog` → `Tag` (belongsToMany) — tags for tagging system
- `Blog` → `BlogComment` (hasMany) — comments if enabled

## Slug Management

- Slugs should be unique and URL-safe (lowercase, hyphens, alphanumeric only).
- Generate automatically from title: `Str::slug($title)`.
- Handle conflicts by appending numeric suffix (e.g., `my-post-1`, `my-post-2`).
- Slugs are immutable (changing slug breaks SEO links); use redirects for URL changes.

## Publishing Workflow

- **Draft**: Author can edit freely; not visible to public.
- **Published**: Visible on blog homepage/archives; can still be edited (show edit notice).
- **Scheduled**: Set `published_at` to future date; auto-publish via scheduled job.
- **Archived**: Hide from listings but keep for historical/SEO reasons.

## SEO & Metadata

- `meta_description` — HTML `<meta>` description for search results.
- `meta_keywords` — Keywords for search engines (optional, less critical).
- `featured_image` — Hero image for post preview and social sharing.
- `excerpt` — Short summary for archive pages and feeds.

## Best Practices

- **Slug immutability**: Once published, don't change slug; set up redirect instead.
- **Content sanitization**: Validate and escape user-provided HTML in content (use HTMLPurifier if allowing rich HTML).
- **Pagination**: Paginate published posts for performance; avoid loading all posts in memory.
- **Caching**: Cache published post list and detail pages to reduce DB queries.
- **Soft deletes**: Use soft deletes for archive/retention purposes.
- **Activity logging**: Log create/update/delete events for audit trail.

## Common Tasks

### Create and Publish a Blog Post

```php
$post = BlogService::create([
    'title' => 'Getting Started with Laravel',
    'content' => '<h2>Introduction</h2>...',
    'author_id' => auth()->id(),
    'status' => 'draft',
    'excerpt' => 'Learn the basics of Laravel framework.',
    'meta_description' => 'A beginner guide to Laravel.',
]);

BlogService::publish($post);
```

### Retrieve Published Posts by Slug

```php
$post = Blog::slug('getting-started-with-laravel')->first();
if ($post) {
    return view('blog.show', compact('post'));
}
```

### List Recent Published Posts

```php
$recentPosts = Blog::published()
    ->orderBy('published_at', 'desc')
    ->limit(10)
    ->get();
```

### Query for Homepage Hero

```php
$featured = Blog::published()
    ->whereNotNull('featured_image')
    ->orderBy('published_at', 'desc')
    ->first();
```
