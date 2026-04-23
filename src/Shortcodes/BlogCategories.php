<?php

namespace Foundry\Shortcodes;

use Foundry\Facades\Blog;
use Vedmant\LaravelShortcodes\Shortcode;

class BlogCategories extends Shortcode
{
    public $attributes = [
        'layout' => ['default' => 'list'],
        'count' => ['default' => true],
        'active' => ['default' => true],
    ];

    protected $casts = [
        'count' => 'boolean',
        'active' => 'boolean',
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $showCount = filter_var($atts['count'], FILTER_VALIDATE_BOOLEAN);
        $onlyActive = filter_var($atts['active'], FILTER_VALIDATE_BOOLEAN);

        // Get categories with or without counts
        $categories = $showCount
            ? Blog::categoriesWithCount($onlyActive)
            : Blog::categories($onlyActive)->map(function ($category) {
                return ['category' => $category, 'count' => 0];
            });

        return $this->view('shortcodes.blog-categories', array_merge($atts, [
            'categories' => $categories,
        ]));
    }
}
