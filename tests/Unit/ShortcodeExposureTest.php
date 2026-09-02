<?php

use Foundry\Services\ShortcodeProcessor;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('exposes array keys as shortcodes', function () {
    $data = [
        'public' => 'visible',
        'secret_key' => 'hidden_value',
        'nested' => [
            'password' => 'super_secret',
        ],
    ];

    $processor = new ShortcodeProcessor;
    $replacements = $processor->process($data);

    $message = 'Public: {{PUBLIC}}, Secret: {{SECRET_KEY}}, Nested: {{NESTED_PASSWORD}}';

    foreach ($replacements as $key => $value) {
        if (is_scalar($value) || is_null($value)) {
            $message = str_replace($key, (string) $value, $message);
        }
    }

    expect($message)->toContain('visible');
    expect($message)->toContain('hidden_value');
    expect($message)->toContain('super_secret');
});
