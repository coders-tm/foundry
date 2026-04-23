<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class BlogMeta extends Shortcode
{
    public $attributes = [];

    public function render($content)
    {
        $atts = $this->atts();
        $options = blog('options', []);
        $category = blog('category', null);
        $featured = isset($options['featured']) ? $options['featured'] : false;

        return $this->view('shortcodes.blog-meta', array_merge($atts, [
            'category' => $category,
            'featured' => $featured,
            'readtime' => isset($options['read_time']) ? $options['read_time'] : 0,
            'datetime' => blog('created_at', now()),
        ]));
    }
}
