<?php

namespace Foundry\Shortcodes;

use Vedmant\LaravelShortcodes\Shortcode;

class ContactForm extends Shortcode
{
    public $attributes = [
        'id' => ['default' => 'contact-from'],
        'class' => ['default' => 'contact-from'],
    ];

    public function render($content)
    {
        $atts = $this->atts();

        return $this->view('shortcodes.contact-form', array_merge($atts, [
            'content' => $content,
        ]));
    }
}
