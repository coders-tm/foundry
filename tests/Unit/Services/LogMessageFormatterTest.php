<?php

use Foundry\Models\Admin;
use Foundry\Models\Log;
use Foundry\Services\Logable;
use Foundry\Services\LogMessageFormatter;
use Foundry\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

beforeEach(function () {
    $this->formatter = new LogMessageFormatter;
});

function makeLog(array $attributes, ?Model $logable = null, ?Admin $admin = null): Log
{
    $log = new Log($attributes);

    if ($logable !== null) {
        $log->setRelation('logable', $logable);
    }

    if ($admin !== null) {
        $log->setRelation('admin', $admin);
    }

    return $log;
}

it('formats created log with resource name and admin', function () {
    $admin = Admin::factory()->make(['first_name' => 'Alice', 'last_name' => 'Smith']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'created', 'message' => null],
        $admin,
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('Admin', $result);
    $this->assertStringContainsString('Alice Smith', $result);
    $this->assertStringContainsString('created', $result);
    $this->assertStringContainsString('Alice Smith', $result);
});

it('formats updated log', function () {
    $admin = Admin::factory()->make(['first_name' => 'Bob', 'last_name' => 'Jones']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'updated', 'message' => null],
        $admin,
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('updated', $result);
    $this->assertStringContainsString('Bob Jones', $result);
});

it('formats deleted log', function () {
    $admin = Admin::factory()->make(['first_name' => 'Carol', 'last_name' => 'White']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'deleted', 'message' => null],
        $admin,
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('deleted', $result);
    $this->assertStringContainsString('Carol White', $result);
});

it('formats restored log', function () {
    $admin = Admin::factory()->make(['first_name' => 'Dave', 'last_name' => 'Green']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'restored', 'message' => null],
        $admin,
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('restored', $result);
});

it('formats force deleted log', function () {
    $admin = Admin::factory()->make(['first_name' => 'Eve', 'last_name' => 'Black']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'force-deleted', 'message' => null],
        $admin,
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('force-deleted', $result);
    $this->assertStringContainsString('Eve Black', $result);
});

it('formats login log in first person', function () {
    $log = makeLog([
        'type' => 'login',
        'message' => null,
        'options' => ['ip' => '127.0.0.1', 'device' => 'Chrome'],
    ]);

    $result = $this->formatter->format($log, firstPerson: true);

    $this->assertStringContainsString('You', $result);
    $this->assertStringContainsString('127.0.0.1', $result);
    $this->assertStringContainsString('Chrome', $result);
});

it('formats login log in third person', function () {
    $admin = Admin::factory()->make(['first_name' => 'Frank', 'last_name' => 'Lee']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        [
            'type' => 'login',
            'message' => null,
            'options' => ['ip' => '192.168.1.1', 'device' => 'Firefox'],
        ],
        $admin,
    );

    $result = $this->formatter->format($log, firstPerson: false);

    $this->assertStringContainsString('Frank Lee', $result);
    $this->assertStringContainsString('192.168.1.1', $result);
    $this->assertStringContainsString('Firefox', $result);
    $this->assertStringNotContainsString('You', $result);
});

it('uses unknown for missing ip and device in login log', function () {
    $log = makeLog([
        'type' => 'login',
        'message' => null,
        'options' => [],
    ]);

    $result = $this->formatter->format($log, firstPerson: true);

    $this->assertStringContainsString('unknown', $result);
});

it('returns raw message for unknown log type', function () {
    $log = makeLog([
        'type' => 'custom-event',
        'message' => 'Something custom happened',
    ]);

    $result = $this->formatter->format($log);

    $this->assertSame('Something custom happened', $result);
});

it('handles null logable gracefully', function () {
    $log = makeLog([
        'type' => 'created',
        'message' => null,
    ]);

    $result = $this->formatter->format($log);

    $this->assertIsString($result);
    $this->assertStringContainsString('System', $result);
});

it('handles null admin gracefully', function () {
    $admin = Admin::factory()->make(['first_name' => 'Gina', 'last_name' => 'Hall']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'deleted', 'message' => null],
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('System', $result);
});

it('falls back to type as resource name when no mapper registered', function () {
    $admin = Admin::factory()->make(['first_name' => 'Henry', 'last_name' => 'Ford']);

    $reflection = new ReflectionClass(Logable::class);
    $prop = $reflection->getProperty('mappers');
    $prop->setAccessible(true);
    $mappers = $prop->getValue();
    unset($mappers[Admin::class]);
    $prop->setValue(null, $mappers);

    $log = makeLog(
        ['type' => 'updated', 'message' => null],
        $admin,
    );

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('Admin', $result);
    $this->assertStringContainsString('updated', $result);
});

it('falls back to record when both type and name are null', function () {
    $log = makeLog(['type' => 'created', 'message' => null]);

    $result = $this->formatter->format($log);

    $this->assertStringContainsString('Record', $result);
});
