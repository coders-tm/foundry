<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class RelatedBlogs extends Shortcode
{
    public $attributes = [
        'limit' => [
            'default' => 3,
        ],
        'title' => [
            'default' => 'Related Articles',
            'description' => 'Title for the related blogs section',
        ],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $related = app('blog')->related(null, 3);

        return $this->view('shortcodes.related-blogs', array_merge($atts, [
            'blogs' => $related,
        ]));
    }
}
