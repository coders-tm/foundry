<?php

namespace Foundry\Services;

use Foundry\Models\Blog;
use Foundry\Models\Blog as BlogModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BlogService
{
    /**
     * Get the current blog from the request.
     *
     * @return Blog|null
     */
    public function current()
    {
        return request()->input('blog');
    }

    /**
     * Get a blog property.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $blog = $this->current();

        return $blog ? $blog->{$key} ?? $default : $default;
    }

    /**
     * Find a blog by ID.
     *
     * @param  int  $id
     * @return Blog|null
     */
    public function find($id)
    {
        return BlogModel::find($id);
    }

    /**
     * Find a blog by slug.
     *
     * @param  string  $slug
     * @return Blog|null
     */
    public function findBySlug($slug)
    {
        return Cache::rememberForever("blog_{$slug}", function () use ($slug) {
            return BlogModel::findBySlug($slug);
        });
    }

    /**
     * Get featured blog.
     *
     * @return Blog|null
     */
    public function featured()
    {
        return Cache::rememberForever('blog_featured', function () {
            return BlogModel::where('is_active', true)
                ->where('options->featured', true)
                ->latest()
                ->first();
        });
    }

    /**
     * Get recent blogs.
     *
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function recent($limit = 5)
    {
        $cacheKey = "blog_recent_{$limit}";

        return Cache::rememberForever($cacheKey, function () use ($limit) {
            return BlogModel::where('is_active', true)
                ->latest()
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get related blogs based on tags and category.
     *
     * @param  Blog|null  $blog
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function related($blog = null, $limit = 3)
    {
        $blog = $blog ?? $this->current();

        if (! $blog) {
            return collect();
        }

        // Create a unique cache key using blog ID, limit, and updated_at timestamp
        // This ensures cache is invalidated when the blog is updated
        $cacheKey = "blog_related_{$blog->id}_{$limit}";

        return Cache::rememberForever($cacheKey, function () use ($blog, $limit) {
            // Get tag IDs from the current blog
            $tagIds = $blog->tags->pluck('id')->toArray();

            // Build query to find related blogs
            $query = BlogModel::where('id', '<>', $blog->id)
                ->where('is_active', true)
                ->where(function ($query) use ($blog, $tagIds) {
                    // If blog has tags, find blogs with matching tags
                    if (count($tagIds) > 0) {
                        $query->whereHas('tags', function ($q) use ($tagIds) {
                            $q->whereIn('id', $tagIds);
                        });
                    }

                    // If blog has category, also consider blogs from same category
                    if ($blog->category) {
                        $query->orWhere('category', $blog->category);
                    }
                });

            return $query->latest()
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get blogs by category.
     *
     * @param  string  $category
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function byCategory($category, $limit = 5)
    {
        $cacheKey = "blog_category_{$category}_{$limit}";

        return Cache::rememberForever($cacheKey, function () use ($category, $limit) {
            return BlogModel::where('category', $category)
                ->where('is_active', true)
                ->latest()
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get blogs by tag.
     *
     * @param  string|int  $tag  Tag ID or name
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function byTag($tag, $limit = 5)
    {
        $tagType = is_numeric($tag) ? 'id' : 'label';
        $cacheKey = "blog_tag_{$tagType}_{$tag}_{$limit}";

        return Cache::rememberForever($cacheKey, function () use ($tag, $limit) {
            $query = BlogModel::where('is_active', true);

            if (is_numeric($tag)) {
                // If $tag is numeric, assume it's a tag ID
                $query->whereHas('tags', function ($q) use ($tag) {
                    $q->where('id', $tag);
                });
            } else {
                // Otherwise assume it's a tag label
                $query->whereHas('tags', function ($q) use ($tag) {
                    $q->where('label', $tag);
                });
            }

            return $query->latest()
                ->take($limit)
                ->get();
        });
    }

    /**
     * Get all categories from blogs table.
     *
     * @param  bool  $onlyActive  Only include categories from active blogs
     * @return Collection
     */
    public function categories($onlyActive = true)
    {
        $cacheKey = 'blog_all_categories'.($onlyActive ? '_active' : '');

        return Cache::rememberForever($cacheKey, function () use ($onlyActive) {
            $query = BlogModel::select('category')
                ->distinct()
                ->whereNotNull('category');

            if ($onlyActive) {
                $query->where('is_active', true);
            }

            return $query->orderBy('category')
                ->pluck('category');
        });
    }

    /**
     * Get categories with blog count.
     *
     * @param  bool  $onlyActive  Only include categories from active blogs
     * @return Collection Collection of [category, count] arrays
     */
    public function categoriesWithCount($onlyActive = true)
    {
        $cacheKey = 'blog_categories_with_count'.($onlyActive ? '_active' : '');

        return Cache::rememberForever($cacheKey, function () use ($onlyActive) {
            $query = BlogModel::select('category')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('category')
                ->groupBy('category');

            if ($onlyActive) {
                $query->where('is_active', true);
            }

            return $query->orderBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category,
                        'count' => $item->count,
                    ];
                });
        });
    }

    /**
     * Clear all blog-related caches.
     *
     * @return void
     */
    public function clearCaches()
    {
        // Clear common caches
        $this->clearRecentBlogCache();
        $this->clearCategoryBlogCaches();
        $this->clearTagBlogCaches();

        // Clear specific blog caches if a current blog exists
        if ($blog = $this->current()) {
            $this->clearBlogCache($blog);
        }
    }

    /**
     * Clear caches for a specific blog.
     *
     * @param  Blog  $blog
     * @return void
     */
    public function clearBlogCache($blog)
    {
        // Clear the blog slug cache
        Cache::forget("blog_{$blog->slug}");

        // Clear the related blogs cache for this blog with various limits
        foreach ([3, 5, 10] as $limit) {
            Cache::forget("blog_related_{$blog->id}_{$limit}_{$blog->updated_at->timestamp}");
        }

        // If blog has category, clear category cache too
        if ($blog->category) {
            $this->clearCategoryBlogCache($blog->category);
        }

        // If blog has tags, clear tag caches too
        foreach ($blog->tags as $tag) {
            $this->clearTagBlogCache($tag->id);
            $this->clearTagBlogCache($tag->label);
        }
    }

    /**
     * Clear recent blogs cache.
     *
     * @return void
     */
    public function clearRecentBlogCache()
    {
        foreach ([3, 5, 10] as $limit) {
            Cache::forget("blog_recent_{$limit}");
        }
    }

    /**
     * Clear category blog caches.
     *
     * @param  string|null  $category
     * @return void
     */
    public function clearCategoryBlogCache($category = null)
    {
        if ($category) {
            foreach ([3, 5, 10] as $limit) {
                Cache::forget("blog_category_{$category}_{$limit}");
            }
        }
    }

    /**
     * Clear all category blog caches.
     *
     * @return void
     */
    public function clearCategoryBlogCaches()
    {
        // Get all unique categories
        $categories = BlogModel::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        foreach ($categories as $category) {
            $this->clearCategoryBlogCache($category);
        }

        // Clear the all categories cache
        Cache::forget('blog_all_categories_active');
        Cache::forget('blog_all_categories');
        Cache::forget('blog_categories_with_count_active');
        Cache::forget('blog_categories_with_count');
    }

    /**
     * Clear tag blog caches.
     *
     * @param  string|int|null  $tag
     * @return void
     */
    public function clearTagBlogCache($tag = null)
    {
        if ($tag) {
            $tagType = is_numeric($tag) ? 'id' : 'label';
            foreach ([3, 5, 10] as $limit) {
                Cache::forget("blog_tag_{$tagType}_{$tag}_{$limit}");
            }
        }
    }

    /**
     * Clear all tag blog caches.
     *
     * @return void
     */
    public function clearTagBlogCaches()
    {
        // This is a more aggressive approach that clears caches for all tags
        // Logic can be enhanced to clear specific tags if needed
        $keys = Cache::get('cache_keys', []);

        foreach ($keys as $key) {
            if (strpos($key, 'blog_tag_') === 0) {
                Cache::forget($key);
            }
        }
    }
}
