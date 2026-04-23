<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class Socials extends Shortcode
{
    public $attributes = [
        'class' => ['default' => 'list-inline'],
        'tooltip' => ['default' => false],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $socials = settings('socials');

        return $this->view('shortcodes.socials', array_merge($atts, [
            'socials' => $socials,
        ]));
    }
}
