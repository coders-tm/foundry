<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\ShortcodeProcessor;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ShortcodeExposureTest extends TestCase
{
    #[Test]
    public function it_exposes_array_keys_as_shortcodes()
    {
        $data = [
            'public' => 'visible',
            'secret_key' => 'hidden_value',
            'nested' => [
                'password' => 'super_secret',
            ],
        ];

        // Simulate replace_short_code helper logic
        $processor = new ShortcodeProcessor;
        $replacements = $processor->process($data);

        $message = 'Public: {{PUBLIC}}, Secret: {{SECRET_KEY}}, Nested: {{NESTED_PASSWORD}}';

        foreach ($replacements as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $message = str_replace($key, (string) $value, $message);
            }
        }

        $this->assertStringContainsString('visible', $message);
        $this->assertStringContainsString('hidden_value', $message);
        $this->assertStringContainsString('super_secret', $message);
    }
}
