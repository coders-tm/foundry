<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class BlogReadtime extends Shortcode
{
    public $attributes = [
        'suffix' => [
            'default' => 'min read',
        ],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $options = blog('options', []);
        $readtime = isset($options['read_time']) ? $options['read_time'] : 0;

        return $readtime.' '.$atts['suffix'];
    }
}
