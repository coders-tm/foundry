<?php

uses(Foundry\Tests\TestCase::class);

beforeEach(function () {
    $this->cast = new \Foundry\Casts\PreserveWhitespaceJson;
    $this->model = new class extends \Illuminate\Database\Eloquent\Model {};
});

it('preserves trailing whitespace in text content', function () {
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

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $this->assertIsString($encoded);

    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    $this->assertSame('Be at least 18 years old or ', $decoded['components'][0]['content']);
    $this->assertSame('have ', $decoded['components'][1]['content']);
});

it('preserves non breaking spaces', function () {
    $originalData = [
        'content' => "Text with\u{00A0}non-breaking\u{00A0}spaces",
    ];

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    $this->assertSame("Text with\u{00A0}non-breaking\u{00A0}spaces", $decoded['content']);
});

it('preserves leading and trailing whitespace', function () {
    $originalData = [
        'content' => '  leading and trailing spaces  ',
        'multiline' => " \n  multi\n  line\n  content  \n ",
    ];

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    $this->assertSame('  leading and trailing spaces  ', $decoded['content']);
    $this->assertSame(" \n  multi\n  line\n  content  \n ", $decoded['multiline']);
});

it('handles complex nested structures with whitespace', function () {
    $originalData = [
        'pages' => [
            [
                'components' => [
                    [
                        'tagName' => 'li',
                        'components' => [
                            [
                                'type' => 'textnode',
                                'content' => 'Be at least 18 years old or ',
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
    $this->assertSame('Be at least 18 years old or ', $components[0]['content']);
    $this->assertSame('have ', $components[1]['components'][0]['content']);
    $this->assertSame('parental consent', $components[2]['content']);
});

it('handles null values', function () {
    $encoded = $this->cast->set($this->model, 'data', null, []);
    $this->assertNull($encoded);

    $decoded = $this->cast->get($this->model, 'data', null, []);
    $this->assertNull($decoded);
});

it('handles already decoded arrays', function () {
    $arrayData = ['key' => 'value with spaces  '];

    $result = $this->cast->get($this->model, 'data', $arrayData, []);
    $this->assertSame($arrayData, $result);
});

it('handles already decoded objects', function () {
    $objectData = (object) ['key' => 'value with spaces  '];

    $result = $this->cast->get($this->model, 'data', $objectData, []);
    $this->assertSame($objectData, $result);
});

it('validates and preserves existing json strings', function () {
    $jsonString = '{"content":"text with trailing space ","another":"value"}';

    $result = $this->cast->set($this->model, 'data', $jsonString, []);
    $this->assertSame($jsonString, $result);
});

it('preserves unicode characters', function () {
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
});

it('preserves various whitespace characters', function () {
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
});

it('handles serialization', function () {
    $data = ['content' => 'text with spaces  '];

    $result = $this->cast->serialize($this->model, 'data', $data, []);
    $this->assertSame($data, $result);
});

it('throws exception for invalid json in get', function () {
    $this->expectException(\JsonException::class);

    $invalidJson = '{"invalid": json}';
    $this->cast->get($this->model, 'data', $invalidJson, []);
});

it('encodes arrays that are not valid json strings', function () {
    $data = ['content' => 'not a json string'];

    $result = $this->cast->set($this->model, 'data', $data, []);
    $this->assertIsString($result);
    $this->assertStringContainsString('not a json string', $result);
});
