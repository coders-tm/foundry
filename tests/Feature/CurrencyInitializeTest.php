<?php

use Foundry\Contracts\Currencyable;
use Foundry\Facades\Currency;
use Foundry\Models\ExchangeRate;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

uses(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');

    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.85]);
    ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.73]);
});

it('initializes with valid currency', function () {
    Currency::initialize('EUR');

    expect(Currency::code())->toBe('EUR');
    expect(Currency::rate())->toBe(0.85);
});

it('initializes with invalid currency falls back to base', function () {
    Currency::initialize('ZZZ');

    expect(Currency::code())->toBe('USD');
    expect(Currency::rate())->toBe(1.0);
});

it('initializes with base currency', function () {
    Currency::initialize('USD');

    expect(Currency::code())->toBe('USD');
    expect(Currency::rate())->toBe(1.0);
});

it('initializes without parameter uses base', function () {
    Currency::initialize();

    expect(Currency::code())->toBe('USD');
    expect(Currency::rate())->toBe(1.0);
});

it('revert returns to base currency', function () {
    Currency::initialize('EUR');
    expect(Currency::code())->toBe('EUR');

    Currency::revert();

    expect(Currency::code())->toBe('USD');
    expect(Currency::rate())->toBe(1.0);
});

it('initializes case insensitively', function () {
    Currency::initialize('eur');

    expect(Currency::code())->toBe('EUR');
    expect(Currency::rate())->toBe(0.85);
});

it('initializes chainable', function () {
    $result = Currency::initialize('GBP');

    expect($result)->toBeInstanceOf(\Foundry\Services\Currency::class);
    expect(Currency::code())->toBe('GBP');
});

it('converts amounts', function () {
    Currency::initialize('EUR');

    expect(Currency::convert(100))->toBe(85.0);
});

it('checks is base', function () {
    expect(Currency::isBase())->toBeTrue();

    Currency::initialize('EUR');
    expect(Currency::isBase())->toBeFalse();

    Currency::revert();
    expect(Currency::isBase())->toBeTrue();
});

it('formats amounts', function () {
    Currency::initialize('EUR');

    $formatted = Currency::format(100);

    expect($formatted)->toBeString();
    expect($formatted)->toContain('85');
});

it('converts array with single field', function () {
    Currency::initialize('EUR');

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

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['id', 'name', 'price', 'currency']);
    expect($result['id'])->toBe(1);
    expect($result['name'])->toBe('Test Product');
    expect($result['price'])->toBe(85.0);
    expect($result['currency'])->toBe('EUR');
});

it('converts array with multiple fields', function () {
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

    expect($result)->toHaveKeys(['id', 'price', 'discount', 'total', 'currency']);
    expect($result['id'])->toBe(1);
    expect($result['price'])->toBe(73.0);
    expect($result['discount'])->toBe(14.6);
    expect($result['total'])->toBe(58.4);
    expect($result['currency'])->toBe('GBP');
});

it('converts array with model', function () {
    Currency::initialize('EUR');

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

    expect($result)->toHaveKeys(['id', 'price', 'sale_price', 'currency']);
    expect($result['id'])->toBe(123);
    expect($result['price'])->toBe(85.0);
    expect($result['sale_price'])->toBe(68.0);
    expect($result['currency'])->toBe('EUR');
});

it('transforms currencyable model', function () {
    Currency::initialize('EUR');

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

    expect($result)->toBeArray();
    expect($result['id'])->toBe(1);
    expect($result['name'])->toBe('Premium Plan');
    expect($result['price'])->toBe(85.0);
    expect($result['freeze_fee'])->toBe(8.5);
    expect($result['currency'])->toBe('EUR');
});

it('transforms collection', function () {
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

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(2);
    expect($result[0]['price'])->toBe(73.0);
    expect($result[1]['price'])->toBe(146.0);
    expect($result[0]['currency'])->toBe('GBP');
});
