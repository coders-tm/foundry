<?php

namespace Foundry\Tests\Feature;

use Foundry\Casts\PreserveWhitespaceJson;
use Foundry\PageBuilder\Models\Page;
use Foundry\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

class PreserveWhitespaceJsonTest extends TestCase
{
    protected PreserveWhitespaceJson $cast;

    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new PreserveWhitespaceJson;
        $this->model = new Page;
    }

    #[Test]
    public function it_preserves_trailing_whitespace_in_text_content()
    {
        $originalData = [
            'components' => [
                [
                    'type' => 'textnode',
                    'content' => 'Be at least 18 years old or ',
                ],
                [
                    'type' => 'textnode',
                    'content' => 'have ',
                ],
            ],
        ];

        // Test encoding (set method)
        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $this->assertIsString($encoded);

        // Test decoding (get method)
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        // Verify trailing spaces are preserved
        $this->assertSame('Be at least 18 years old or ', $decoded['components'][0]['content']);
        $this->assertSame('have ', $decoded['components'][1]['content']);
    }

    #[Test]
    public function it_preserves_non_breaking_spaces()
    {
        $originalData = [
            'content' => "Text with\u{00A0}non-breaking\u{00A0}spaces",
        ];

        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        $this->assertSame("Text with\u{00A0}non-breaking\u{00A0}spaces", $decoded['content']);
    }

    #[Test]
    public function it_preserves_leading_and_trailing_whitespace()
    {
        $originalData = [
            'content' => '  leading and trailing spaces  ',
            'multiline' => " \n  multi\n  line\n  content  \n ",
        ];

        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        $this->assertSame('  leading and trailing spaces  ', $decoded['content']);
        $this->assertSame(" \n  multi\n  line\n  content  \n ", $decoded['multiline']);
    }

    #[Test]
    public function it_handles_complex_nested_structures_with_whitespace()
    {
        $originalData = [
            'pages' => [
                [
                    'components' => [
                        [
                            'tagName' => 'li',
                            'components' => [
                                [
                                    'type' => 'textnode',
                                    'content' => 'Be at least 18 years old or ',
                                ],
                                [
                                    'type' => 'link',
                                    'components' => [
                                        [
                                            'type' => 'textnode',
                                            'content' => 'have ',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'textnode',
                                    'content' => 'parental consent',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        $components = $decoded['pages'][0]['components'][0]['components'];
        $this->assertSame('Be at least 18 years old or ', $components[0]['content']);
        $this->assertSame('have ', $components[1]['components'][0]['content']);
        $this->assertSame('parental consent', $components[2]['content']);
    }

    #[Test]
    public function it_handles_null_values()
    {
        $encoded = $this->cast->set($this->model, 'data', null, []);
        $this->assertNull($encoded);

        $decoded = $this->cast->get($this->model, 'data', null, []);
        $this->assertNull($decoded);
    }

    #[Test]
    public function it_handles_already_decoded_arrays()
    {
        $arrayData = ['key' => 'value with spaces  '];

        $result = $this->cast->get($this->model, 'data', $arrayData, []);
        $this->assertSame($arrayData, $result);
    }

    #[Test]
    public function it_handles_already_decoded_objects()
    {
        $objectData = (object) ['key' => 'value with spaces  '];

        $result = $this->cast->get($this->model, 'data', $objectData, []);
        $this->assertSame($objectData, $result);
    }

    #[Test]
    public function it_validates_and_preserves_existing_json_strings()
    {
        $jsonString = '{"content":"text with trailing space ","another":"value"}';

        $result = $this->cast->set($this->model, 'data', $jsonString, []);
        $this->assertSame($jsonString, $result);
    }

    #[Test]
    public function it_preserves_unicode_characters()
    {
        $originalData = [
            'emoji' => '🎉 Party time! 🎊 ',
            'unicode' => 'Café münü  ',
            'mixed' => 'Mixed 🌟 content with spaces  ',
        ];

        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        $this->assertSame('🎉 Party time! 🎊 ', $decoded['emoji']);
        $this->assertSame('Café münü  ', $decoded['unicode']);
        $this->assertSame('Mixed 🌟 content with spaces  ', $decoded['mixed']);
    }

    #[Test]
    public function it_preserves_various_whitespace_characters()
    {
        $originalData = [
            'tab' => "content\twith\ttabs\t",
            'newline' => "content\nwith\nnewlines\n",
            'carriage_return' => "content\rwith\rcarriage\r",
            'mixed_whitespace' => " \t\n\r mixed \t\n\r whitespace \t\n\r ",
        ];

        $encoded = $this->cast->set($this->model, 'data', $originalData, []);
        $decoded = $this->cast->get($this->model, 'data', $encoded, []);

        $this->assertSame("content\twith\ttabs\t", $decoded['tab']);
        $this->assertSame("content\nwith\nnewlines\n", $decoded['newline']);
        $this->assertSame("content\rwith\rcarriage\r", $decoded['carriage_return']);
        $this->assertSame(" \t\n\r mixed \t\n\r whitespace \t\n\r ", $decoded['mixed_whitespace']);
    }

    #[Test]
    public function it_handles_serialization()
    {
        $data = ['content' => 'text with spaces  '];

        $result = $this->cast->serialize($this->model, 'data', $data, []);
        $this->assertSame($data, $result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json_in_get()
    {
        $this->expectException(\JsonException::class);

        $invalidJson = '{"invalid": json}';
        $this->cast->get($this->model, 'data', $invalidJson, []);
    }

    #[Test]
    public function it_encodes_arrays_that_are_not_valid_json_strings()
    {
        $data = ['content' => 'not a json string'];

        $result = $this->cast->set($this->model, 'data', $data, []);
        $this->assertIsString($result);
        $this->assertStringContainsString('not a json string', $result);
    }
}
