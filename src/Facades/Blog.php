<?php

namespace Foundry\Facades;

use Foundry\Services\BlogService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foundry\Models\Blog|null current()
 * @method static mixed get($key, $default = null)
 * @method static \Foundry\Models\Blog|null find($id)
 * @method static \Foundry\Models\Blog|null findBySlug($slug)
 * @method static \Illuminate\Database\Eloquent\Collection recent($limit = 5)
 * @method static \Illuminate\Database\Eloquent\Collection related($blog = null, $limit = 3)
 * @method static \Illuminate\Database\Eloquent\Collection byCategory($category, $limit = 5)
 * @method static \Illuminate\Database\Eloquent\Collection byTag($tag, $limit = 5)
 * @method static \Illuminate\Support\Collection categories($onlyActive = true)
 * @method static \Illuminate\Support\Collection categoriesWithCount($onlyActive = true)
 * @method static void clearCaches()
 * @method static void clearBlogCache(\Foundry\Models\Blog $blog)
 * @method static void clearRecentBlogCache()
 * @method static void clearCategoryBlogCache(string|null $category)
 * @method static void clearCategoryBlogCaches()
 * @method static void clearTagBlogCache(string|int|null $tag)
 * @method static void clearTagBlogCaches()
 *
 * @see BlogService
 */
class Blog extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'blog';
    }
}
