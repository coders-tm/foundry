<?php

namespace Foundry\Shortcodes;

use Foundry\Models\Blog;
use Vedmant\LaravelShortcodes\Shortcode;

class Blogs extends Shortcode
{
    public $attributes = [
        'class' => ['default' => 'blogs'],
        'paginate' => ['default' => 12],
        'layout' => ['default' => 'default'],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $blogs = Blog::onlyActive()->orderBy('created_at', 'desc');
        $featured = app('blog')->featured();

        if (request()->filled('category')) {
            $blogs = $blogs->where('category', request()->input('category'));
        }

        if (request()->filled('tag')) {
            $blogs = $blogs->whereHas('tags', function ($query) {
                $query->where('slug', request()->input('tag'));
            });
        }

        if (request()->filled('search')) {
            $blogs = $blogs->where('title', 'like', '%'.request()->input('search').'%');
        }

        // Exclude featured post from list if exists and on first page (or always? usually always if shown separately)
        if ($featured) {
            $blogs = $blogs->where('id', '<>', $featured->id);
        }

        $blogs = $blogs->paginate($atts['paginate']);

        return $this->view('shortcodes.blogs', array_merge($atts, [
            'content' => $content,
            'blogs' => $blogs,
            'featured' => $featured,
        ]));
    }
}
