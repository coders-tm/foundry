<?php

namespace Foundry\Shortcodes;

use Foundry\Models\Blog as Post;
use Vedmant\LaravelShortcodes\Shortcode;

class Blog extends Shortcode
{
    public $attributes = [
        'class' => ['default' => 'blog-page'],
        'layout' => ['default' => 'default'],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $blog = blog() ?? Post::inRandomOrder()->first();

        if (! $blog) {
            return '';
        }

        // Get related blogs if available
        $related = app('blog')->related($blog, 3);

        return $this->view('shortcodes.blog', array_merge(
            $atts,
            $blog->toArray(),
            ['related' => $related]
        ));
    }
}
