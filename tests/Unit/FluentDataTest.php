<?php

use Foundry\Support\FluentData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

uses(TestCase::class);

it('can be instantiated with array', function () {
    $data = ['foo' => 'bar'];
    $fluent = new FluentData($data);

    $this->assertEquals('bar', $fluent->foo);
    $this->assertEquals('bar', $fluent['foo']);
});

it('can be instantiated with object', function () {
    $data = (object) ['foo' => 'bar'];
    $fluent = new FluentData($data);

    $this->assertEquals('bar', $fluent->foo);
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

    $this->assertInstanceOf(FluentData::class, $fluent->user);
    $this->assertEquals('John', $fluent->user->name);
    $this->assertInstanceOf(FluentData::class, $fluent->user->address);
    $this->assertEquals('New York', $fluent->user->address->city);
});

it('returns safe object for undefined keys', function () {
    $fluent = new FluentData([]);

    $this->assertNull($fluent->missing);
    $this->assertNull($fluent->missing->nested);
});

it('is countable', function () {
    $data = ['a' => 1, 'b' => 2];
    $fluent = new FluentData($data);

    $this->assertCount(2, $fluent);
    $this->assertEquals(2, $fluent->count());

    $empty = new FluentData([]);
    $this->assertCount(0, $empty);
    $this->assertEquals(0, $empty->count());
});

it('is iterable', function () {
    $data = ['a' => 1, 'b' => 2];
    $fluent = new FluentData($data);

    $result = [];
    foreach ($fluent as $key => $value) {
        $result[$key] = $value;
    }

    $this->assertEquals($data, $result);
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
        $this->assertInstanceOf(FluentData::class, $item);
    }
});

it('handles collection', function () {
    $collection = new Collection(['key' => 'value']);
    $fluent = new FluentData($collection);

    $this->assertEquals('value', $fluent->key);
});

it('handles explicit type conversions', function () {
    $data = [
        'int_val' => 42,
        'float_val' => 10.5,
        'string_num' => '100',
        'null_val' => null,
    ];
    $fluent = new FluentData($data);

    $this->assertEquals(42, $fluent->int_val);
    $this->assertEquals(10.5, $fluent->float_val);
    $this->assertNull($fluent->missing);
});
