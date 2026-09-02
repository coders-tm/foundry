<?php

use Foundry\Casts\PreserveWhitespaceJson;
use Foundry\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

beforeEach(function () {
    $this->cast = new PreserveWhitespaceJson;
    $this->model = new class extends Model {};
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
    expect($encoded)->toBeString();

    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    expect($decoded['components'][0]['content'])->toBe('Be at least 18 years old or ');
    expect($decoded['components'][1]['content'])->toBe('have ');
});

it('preserves non breaking spaces', function () {
    $originalData = [
        'content' => "Text with\u{00A0}non-breaking\u{00A0}spaces",
    ];

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    expect($decoded['content'])->toBe("Text with\u{00A0}non-breaking\u{00A0}spaces");
});

it('preserves leading and trailing whitespace', function () {
    $originalData = [
        'content' => '  leading and trailing spaces  ',
        'multiline' => " \n  multi\n  line\n  content  \n ",
    ];

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    expect($decoded['content'])->toBe('  leading and trailing spaces  ');
    expect($decoded['multiline'])->toBe(" \n  multi\n  line\n  content  \n ");
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
    expect($components[0]['content'])->toBe('Be at least 18 years old or ');
    expect($components[1]['components'][0]['content'])->toBe('have ');
    expect($components[2]['content'])->toBe('parental consent');
});

it('handles null values', function () {
    $encoded = $this->cast->set($this->model, 'data', null, []);
    expect($encoded)->toBeNull();

    $decoded = $this->cast->get($this->model, 'data', null, []);
    expect($decoded)->toBeNull();
});

it('handles already decoded arrays', function () {
    $arrayData = ['key' => 'value with spaces  '];

    $result = $this->cast->get($this->model, 'data', $arrayData, []);
    expect($result)->toEqual($arrayData);
});

it('handles already decoded objects', function () {
    $objectData = (object) ['key' => 'value with spaces  '];

    $result = $this->cast->get($this->model, 'data', $objectData, []);
    expect($result)->toEqual($objectData);
});

it('validates and preserves existing json strings', function () {
    $jsonString = '{"content":"text with trailing space ","another":"value"}';

    $result = $this->cast->set($this->model, 'data', $jsonString, []);
    expect($result)->toBe($jsonString);
});

it('preserves unicode characters', function () {
    $originalData = [
        'emoji' => '🎉 Party time! 🎊 ',
        'unicode' => 'Café münü  ',
        'mixed' => 'Mixed 🌟 content with spaces  ',
    ];

    $encoded = $this->cast->set($this->model, 'data', $originalData, []);
    $decoded = $this->cast->get($this->model, 'data', $encoded, []);

    expect($decoded['emoji'])->toBe('🎉 Party time! 🎊 ');
    expect($decoded['unicode'])->toBe('Café münü  ');
    expect($decoded['mixed'])->toBe('Mixed 🌟 content with spaces  ');
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

    expect($decoded['tab'])->toBe("content\twith\ttabs\t");
    expect($decoded['newline'])->toBe("content\nwith\nnewlines\n");
    expect($decoded['carriage_return'])->toBe("content\rwith\rcarriage\r");
    expect($decoded['mixed_whitespace'])->toBe(" \t\n\r mixed \t\n\r whitespace \t\n\r ");
});

it('handles serialization', function () {
    $data = ['content' => 'text with spaces  '];

    $result = $this->cast->serialize($this->model, 'data', $data, []);
    expect($result)->toEqual($data);
});

it('throws exception for invalid json in get', function () {
    expect(fn () => $this->cast->get($this->model, 'data', '{"invalid": json}', []))->toThrow(JsonException::class);
});

it('encodes arrays that are not valid json strings', function () {
    $data = ['content' => 'not a json string'];

    $result = $this->cast->set($this->model, 'data', $data, []);
    expect($result)->toBeString();
    expect($result)->toContain('not a json string');
});
