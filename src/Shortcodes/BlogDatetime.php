<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class BlogDatetime extends Shortcode
{
    public $attributes = [
        'format' => [
            'default' => 'Y-m-d H:i:s',
        ],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $dateTime = blog('created_at', now());

        return $dateTime->format($atts['format']);
    }
}
