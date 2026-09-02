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

    expect($result)->toContain('Admin');
    expect($result)->toContain('Alice Smith');
    expect($result)->toContain('created');
    expect($result)->toContain('Alice Smith');
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

    expect($result)->toContain('updated');
    expect($result)->toContain('Bob Jones');
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

    expect($result)->toContain('deleted');
    expect($result)->toContain('Carol White');
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

    expect($result)->toContain('restored');
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

    expect($result)->toContain('force-deleted');
    expect($result)->toContain('Eve Black');
});

it('formats login log in first person', function () {
    $log = makeLog([
        'type' => 'login',
        'message' => null,
        'options' => ['ip' => '127.0.0.1', 'device' => 'Chrome'],
    ]);

    $result = $this->formatter->format($log, firstPerson: true);

    expect($result)->toContain('You');
    expect($result)->toContain('127.0.0.1');
    expect($result)->toContain('Chrome');
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

    expect($result)->toContain('Frank Lee');
    expect($result)->toContain('192.168.1.1');
    expect($result)->toContain('Firefox');
    expect($result)->not->toContain('You');
});

it('uses unknown for missing ip and device in login log', function () {
    $log = makeLog([
        'type' => 'login',
        'message' => null,
        'options' => [],
    ]);

    $result = $this->formatter->format($log, firstPerson: true);

    expect($result)->toContain('unknown');
});

it('returns raw message for unknown log type', function () {
    $log = makeLog([
        'type' => 'custom-event',
        'message' => 'Something custom happened',
    ]);

    $result = $this->formatter->format($log);

    expect($result)->toBe('Something custom happened');
});

it('handles null logable gracefully', function () {
    $log = makeLog([
        'type' => 'created',
        'message' => null,
    ]);

    $result = $this->formatter->format($log);

    expect($result)->toBeString();
    expect($result)->toContain('System');
});

it('handles null admin gracefully', function () {
    $admin = Admin::factory()->make(['first_name' => 'Gina', 'last_name' => 'Hall']);

    Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

    $log = makeLog(
        ['type' => 'deleted', 'message' => null],
        $admin,
    );

    $result = $this->formatter->format($log);

    expect($result)->toContain('System');
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

    expect($result)->toContain('Admin');
    expect($result)->toContain('updated');
});

it('falls back to record when both type and name are null', function () {
    $log = makeLog(['type' => 'created', 'message' => null]);

    $result = $this->formatter->format($log);

    expect($result)->toContain('Record');
});
