<?php

namespace Foundry\Providers;

use Foundry\Shortcodes as Component;
use Illuminate\Support\ServiceProvider;
use Vedmant\LaravelShortcodes\Facades\Shortcodes;

class ShortcodeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Shortcodes::add([
            'plans' => Component\Plans::class,
            'contact-form' => Component\ContactForm::class,
            'blogs' => Component\Blogs::class,
            'menu' => Component\Menu::class,
            'socials' => Component\Socials::class,
            'blog' => Component\Blog::class,
            'recent-blogs' => Component\RecentBlogs::class,
            'blog-tags' => Component\BlogTags::class,
            'blog-categories' => Component\BlogCategories::class,
            'blog-datetime' => Component\BlogDatetime::class,
            'blog-readtime' => Component\BlogReadtime::class,
            'blog-meta' => Component\BlogMeta::class,
            'related-blogs' => Component\RelatedBlogs::class,
        ]);

        Shortcodes::add('blog-title', function ($atts, $content, $tag, $manager) {
            return request()->input('blog.title');
        });

        Shortcodes::add('page-title', function ($atts, $content, $tag, $manager) {
            return request()->input('page.title');
        });
    }
}
