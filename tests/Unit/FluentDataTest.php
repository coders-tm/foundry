<?php

use Foundry\Support\FluentData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

uses(TestCase::class);

it('can be instantiated with array', function () {
    $data = ['foo' => 'bar'];
    $fluent = new FluentData($data);

    expect($fluent->foo)->toBe('bar');
    expect($fluent['foo'])->toBe('bar');
});

it('can be instantiated with object', function () {
    $data = (object) ['foo' => 'bar'];
    $fluent = new FluentData($data);

    expect($fluent->foo)->toBe('bar');
});

it('supports nested access', function () {
    $data = [
        'user' => [
            'name' => 'John',
            'address' => [
                'city' => 'New York',
            ],
        ],
    ];
    $fluent = new FluentData($data);

    expect($fluent->user)->toBeInstanceOf(FluentData::class);
    expect($fluent->user->name)->toBe('John');
    expect($fluent->user->address)->toBeInstanceOf(FluentData::class);
    expect($fluent->user->address->city)->toBe('New York');
});

it('returns safe object for undefined keys', function () {
    $fluent = new FluentData([]);

    expect($fluent->missing)->toBeNull();
    expect($fluent->missing->nested)->toBeNull();
});

it('is countable', function () {
    $data = ['a' => 1, 'b' => 2];
    $fluent = new FluentData($data);

    expect($fluent)->toHaveCount(2);
    expect($fluent->count())->toBe(2);

    $empty = new FluentData([]);
    expect($empty)->toHaveCount(0);
    expect($empty->count())->toBe(0);
});

it('is iterable', function () {
    $data = ['a' => 1, 'b' => 2];
    $fluent = new FluentData($data);

    $result = [];
    foreach ($fluent as $key => $value) {
        $result[$key] = $value;
    }

    expect($result)->toBe($data);
});

it('wraps children during iteration', function () {
    $data = [
        'items' => [
            ['id' => 1],
            ['id' => 2],
        ],
    ];
    $fluent = new FluentData($data);

    foreach ($fluent->items as $item) {
        expect($item)->toBeInstanceOf(FluentData::class);
    }
});

it('handles collection', function () {
    $collection = new Collection(['key' => 'value']);
    $fluent = new FluentData($collection);

    expect($fluent->key)->toBe('value');
});

it('handles explicit type conversions', function () {
    $data = [
        'int_val' => 42,
        'float_val' => 10.5,
        'string_num' => '100',
        'null_val' => null,
    ];
    $fluent = new FluentData($data);

    expect($fluent->int_val)->toBe(42);
    expect($fluent->float_val)->toBe(10.5);
    expect($fluent->missing)->toBeNull();
});
