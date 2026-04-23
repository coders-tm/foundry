<?php

namespace Foundry\Tests\Feature;

use Foundry\Contracts\Currencyable;
use Foundry\Facades\Currency;
use Foundry\Models\ExchangeRate;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CurrencyInitializeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.currency', 'USD');

        // Create some exchange rates
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.85]);
        ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.73]);
    }

    #[Test]
    public function test_initialize_with_valid_currency()
    {
        Currency::initialize('EUR');

        $this->assertEquals('EUR', Currency::code());
        $this->assertEquals(0.85, Currency::rate());
    }

    #[Test]
    public function test_initialize_with_invalid_currency_falls_back_to_base()
    {
        // Try to initialize with non-existent currency
        Currency::initialize('ZZZ');

        // Should fallback to base currency (USD)
        $this->assertEquals('USD', Currency::code());
        $this->assertEquals(1.0, Currency::rate());
    }

    #[Test]
    public function test_initialize_with_base_currency()
    {
        Currency::initialize('USD');

        $this->assertEquals('USD', Currency::code());
        $this->assertEquals(1.0, Currency::rate());
    }

    #[Test]
    public function test_initialize_without_parameter_uses_base()
    {
        Currency::initialize();

        $this->assertEquals('USD', Currency::code());
        $this->assertEquals(1.0, Currency::rate());
    }

    #[Test]
    public function test_revert_returns_to_base_currency()
    {
        // First set to EUR
        Currency::initialize('EUR');
        $this->assertEquals('EUR', Currency::code());

        // Then revert to base
        Currency::revert();

        $this->assertEquals('USD', Currency::code());
        $this->assertEquals(1.0, Currency::rate());
    }

    #[Test]
    public function test_initialize_is_case_insensitive()
    {
        Currency::initialize('eur');

        $this->assertEquals('EUR', Currency::code());
        $this->assertEquals(0.85, Currency::rate());
    }

    #[Test]
    public function test_initialize_chainable()
    {
        $result = Currency::initialize('GBP');

        // Should return the service instance (chainable)
        $this->assertInstanceOf(\Foundry\Services\Currency::class, $result);
        $this->assertEquals('GBP', Currency::code());
    }

    #[Test]
    public function test_convert_method()
    {
        Currency::initialize('EUR');

        // 100 USD * 0.85 = 85 EUR
        $this->assertEquals(85.0, Currency::convert(100));
    }

    #[Test]
    public function test_is_base_method()
    {
        // Default is base currency
        $this->assertTrue(Currency::isBase());

        // After initializing to different currency
        Currency::initialize('EUR');
        $this->assertFalse(Currency::isBase());

        // After reverting
        Currency::revert();
        $this->assertTrue(Currency::isBase());
    }

    #[Test]
    public function test_format_method()
    {
        Currency::initialize('EUR');

        $formatted = Currency::format(100);
        // Should contain the converted amount formatted
        $this->assertIsString($formatted);
        $this->assertStringContainsString('85', $formatted);
    }

    #[Test]
    public function test_to_array_with_single_field()
    {
        Currency::initialize('EUR');

        // Create a mock model with toArray() method
        $data = new class
        {
            public $id = 1;

            public $name = 'Test Product';

            public $price = 100;

            public function toArray()
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'price' => $this->price,
                ];
            }
        };

        $result = Currency::toArray($data, ['price']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('currency', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(85.0, $result['price']); // 100 * 0.85 (converted)
        $this->assertEquals('EUR', $result['currency']);
    }

    #[Test]
    public function test_to_array_with_multiple_fields()
    {
        Currency::initialize('GBP');

        $data = new class
        {
            public $id = 1;

            public $price = 100;

            public $discount = 20;

            public $total = 80;

            public function toArray()
            {
                return [
                    'id' => $this->id,
                    'price' => $this->price,
                    'discount' => $this->discount,
                    'total' => $this->total,
                ];
            }
        };

        $result = Currency::toArray($data, ['price', 'discount', 'total']);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('discount', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('currency', $result);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals(73.0, $result['price']); // 100 * 0.73
        $this->assertEquals(14.6, $result['discount']); // 20 * 0.73
        $this->assertEquals(58.4, $result['total']); // 80 * 0.73
        $this->assertEquals('GBP', $result['currency']);
    }

    #[Test]
    public function test_to_array_with_model()
    {
        Currency::initialize('EUR');

        // Create a simple mock model
        $model = new class
        {
            public $id = 123;

            public $price = 100;

            public $sale_price = 80;

            public function toArray()
            {
                return [
                    'id' => $this->id,
                    'price' => $this->price,
                    'sale_price' => $this->sale_price,
                ];
            }
        };

        $result = Currency::toArray($model, ['price', 'sale_price']);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(123, $result['id']);
        $this->assertEquals(85.0, $result['price']);
        $this->assertEquals(68.0, $result['sale_price']);
        $this->assertEquals('EUR', $result['currency']);
    }

    #[Test]
    public function test_transform_with_currencyable_model()
    {
        Currency::initialize('EUR');

        // Create a mock Currencyable model
        $model = new class implements Currencyable
        {
            public $id = 1;

            public $name = 'Premium Plan';

            public $price = 100;

            public $freeze_fee = 10;

            public function toArray()
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'price' => $this->price,
                    'freeze_fee' => $this->freeze_fee,
                ];
            }

            public function getCurrencyFields(): array
            {
                return ['price', 'freeze_fee'];
            }
        };

        $result = Currency::transform($model);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Premium Plan', $result['name']);
        $this->assertEquals(85.0, $result['price']); // 100 * 0.85
        $this->assertEquals(8.5, $result['freeze_fee']); // 10 * 0.85
        $this->assertEquals('EUR', $result['currency']);
    }

    #[Test]
    public function test_transform_with_collection()
    {
        Currency::initialize('GBP');

        $collection = collect([
            new class implements Currencyable
            {
                public $id = 1;

                public $price = 100;

                public function toArray()
                {
                    return ['id' => $this->id, 'price' => $this->price];
                }

                public function getCurrencyFields(): array
                {
                    return ['price'];
                }
            },
            new class implements Currencyable
            {
                public $id = 2;

                public $price = 200;

                public function toArray()
                {
                    return ['id' => $this->id, 'price' => $this->price];
                }

                public function getCurrencyFields(): array
                {
                    return ['price'];
                }
            },
        ]);

        $result = Currency::transform($collection);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals(73.0, $result[0]['price']); // 100 * 0.73
        $this->assertEquals(146.0, $result[1]['price']); // 200 * 0.73
        $this->assertEquals('GBP', $result[0]['currency']);
    }
}
