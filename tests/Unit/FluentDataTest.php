<?php

namespace Foundry\Tests\Unit;

use Foundry\Support\FluentData;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class FluentDataTest extends TestCase
{
    public function test_it_can_be_instantiated_with_array()
    {
        $data = ['foo' => 'bar'];
        $fluent = new FluentData($data);

        $this->assertEquals('bar', $fluent->foo);
        $this->assertEquals('bar', $fluent['foo']);
    }

    public function test_it_can_be_instantiated_with_object()
    {
        $data = (object) ['foo' => 'bar'];
        $fluent = new FluentData($data);

        $this->assertEquals('bar', $fluent->foo);
    }

    public function test_it_supports_nested_access()
    {
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
    }

    public function test_it_returns_safe_object_for_undefined_keys()
    {
        $fluent = new FluentData([]);

        $this->assertNull($fluent->missing);
        $this->assertNull($fluent->missing->nested);
    }

    public function test_it_is_countable()
    {
        $data = ['a' => 1, 'b' => 2];
        $fluent = new FluentData($data);

        $this->assertCount(2, $fluent);
        $this->assertEquals(2, $fluent->count());

        $empty = new FluentData([]);
        $this->assertCount(0, $empty);
        $this->assertEquals(0, $empty->count());
    }

    public function test_it_is_iterable()
    {
        $data = ['a' => 1, 'b' => 2];
        $fluent = new FluentData($data);

        $result = [];
        foreach ($fluent as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertEquals($data, $result);
    }

    public function test_iteration_wraps_children()
    {
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
    }

    public function test_it_handles_collection()
    {
        $collection = new Collection(['key' => 'value']);
        $fluent = new FluentData($collection);

        $this->assertEquals('value', $fluent->key);
    }

    public function test_explicit_type_conversions()
    {
        $data = [
            'int_val' => 42,
            'float_val' => 10.5,
            'string_num' => '100',
            'null_val' => null,
        ];
        $fluent = new FluentData($data);

        // Test existing properties
        $this->assertEquals(42, $fluent->int_val); // Direct access unwrapped
        $this->assertEquals(10.5, $fluent->float_val);

        // Test missing properties (which return FluentData instances)
        $this->assertNull($fluent->missing);
    }
}
