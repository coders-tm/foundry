<?php

namespace Foundry\Shortcodes;

use Foundry\Facades\Currency;
use Foundry\Models\Subscription\Plan;
use Vedmant\LaravelShortcodes\Shortcode;

class Plans extends Shortcode
{
    public $attributes = [
        'class' => ['default' => 'plans'],
        'layout' => ['default' => 'default'],
    ];

    public function render($content)
    {
        $atts = $this->atts();
        $plans = Plan::onlyActive()->get();

        $plans = Currency::transform($plans);

        $plans = collect($plans)->map(function ($item) {
            return array_merge($item, [
                'cur_symbol' => currency_symbol(Currency::code()),
            ]);
        });

        return $this->view('shortcodes.plans', array_merge($atts, [
            'content' => $content,
            'plans' => $plans,
        ]));
    }
}
