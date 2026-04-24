<?php

namespace Foundry\Models;

use Foundry\Models\Blog\Comment;
use Foundry\Models\Blog\Tag;
use Foundry\Concerns\Core;
use Foundry\Concerns\Fileable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Blog extends Model
{
    use Core, Fileable, HasSlug;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'is_active',
        'options',
        'category',
    ];

    protected $with = [
        'thumbnail',
        'tags',
    ];

    protected $logIgnore = ['options'];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'options' => 'json',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function createComment(array $attributes = [])
    {
        $comment = new Comment($attributes);
        $comment->user()->associate(user());

        return $this->comments()->save($comment);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable', 'blogs_taggables');
    }

    public function setTags(array $items = [])
    {
        $tags = [];

        foreach ($items as $item) {
            $tag = Tag::firstOrCreate([
                'label' => $item['label'],
            ]);
            $tags[] = $tag->id;
        }

        $this->tags()->sync($tags);

        return $this;
    }

    public static function findBySlug(string $slug)
    {
        return static::where('slug', $slug)
            ->where('is_active', true)
            ->with('tags')
            ->firstOrFail();
    }

    public static function setReadTimeOption($description, array $options = [])
    {
        $description = strip_tags($description ?? '');
        $wordCount = str_word_count($description);
        $readTime = max(1, ceil($wordCount / 200));
        $options['read_time'] = $readTime;

        return $options;
    }

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($blog) {
            $blog->options = static::setReadTimeOption($blog->description, $blog->options ?? []);
        });

        static::updating(function ($blog) {
            if ($blog->isDirty('description')) {
                $blog->options = static::setReadTimeOption($blog->description, $blog->options ?? []);
            }
        });

        static::updated(function ($blog) {
            // Clear old slug cache if slug changed
            if ($slug = $blog->getOriginal('slug')) {
                Cache::forget("blog_{$slug}");
            }

            // Clear current blog cache
            Cache::forget("blog_{$blog->slug}");

            // Clear related caches using the BlogService
            app('blog')->clearBlogCache($blog);
            app('blog')->clearRecentBlogCache();
        });

        static::created(function ($blog) {
            // Clear recent blogs cache when a new blog is created
            app('blog')->clearRecentBlogCache();
        });

        static::deleted(function ($blog) {
            // Clear all caches related to this blog
            Cache::forget("blog_{$blog->slug}");
            app('blog')->clearBlogCache($blog);
            app('blog')->clearRecentBlogCache();
        });

        static::addGlobalScope('short_desc', function ($query) {
            $url = config('app.url');
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                // MySQL-specific implementation
                $query->select('*')
                    ->addSelect(DB::raw("SUBSTRING_INDEX(REGEXP_REPLACE(REGEXP_REPLACE(description, '<[^>]+>', ' '), '[[:space:]]+', ' '), ' ', 20) AS short_desc"))
                    ->addSelect(DB::raw("CONCAT('{$url}/blog/', slug) as url"));
            } else {
                // SQLite and other databases - use simpler approach
                $query->select('*')
                    ->addSelect(DB::raw("SUBSTR(REPLACE(REPLACE(REPLACE(description, '<', ' '), '>', ' '), '  ', ' '), 1, 200) AS short_desc"))
                    ->addSelect(DB::raw("'{$url}/blog/' || slug as url"));
            }
        });
    }
}
