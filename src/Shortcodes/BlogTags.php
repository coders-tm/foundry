<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class BlogTags extends Shortcode
{
    public $attributes = [
        'style' => [
            'default' => 'default',
        ],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $tags = blog('tags', []);

        return $this->view('shortcodes.blog-tags', array_merge($atts, [
            'tags' => $tags,
        ]));
    }
}
