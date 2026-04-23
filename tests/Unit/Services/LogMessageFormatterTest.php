<?php

namespace Foundry\Tests\Unit\Services;

use Foundry\Models\Admin;
use Foundry\Models\Log;
use Foundry\Services\Logable;
use Foundry\Services\LogMessageFormatter;
use Foundry\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

class LogMessageFormatterTest extends TestCase
{
    protected LogMessageFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new LogMessageFormatter;
    }

    /**
     * Build an unsaved Log model populated with the given attributes and
     * optionally associate a logable / admin relationship via setRelation().
     */
    protected function makeLog(array $attributes, ?Model $logable = null, ?Admin $admin = null): Log
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

    #[Test]
    public function it_formats_created_log_with_resource_name_and_admin(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Alice', 'last_name' => 'Smith']);

        // Register a mapper so getResource() returns a useful name.
        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
            ['type' => 'created', 'message' => null],
            $admin,
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('Admin', $result);
        $this->assertStringContainsString('Alice Smith', $result);
        $this->assertStringContainsString('created', $result);
        $this->assertStringContainsString('Alice Smith', $result);
    }

    #[Test]
    public function it_formats_updated_log(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Bob', 'last_name' => 'Jones']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
            ['type' => 'updated', 'message' => null],
            $admin,
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('updated', $result);
        $this->assertStringContainsString('Bob Jones', $result);
    }

    #[Test]
    public function it_formats_deleted_log(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Carol', 'last_name' => 'White']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
            ['type' => 'deleted', 'message' => null],
            $admin,
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('deleted', $result);
        $this->assertStringContainsString('Carol White', $result);
    }

    #[Test]
    public function it_formats_restored_log(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Dave', 'last_name' => 'Green']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
            ['type' => 'restored', 'message' => null],
            $admin,
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('restored', $result);
    }

    #[Test]
    public function it_formats_force_deleted_log(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Eve', 'last_name' => 'Black']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
            ['type' => 'force-deleted', 'message' => null],
            $admin,
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('force-deleted', $result);
        $this->assertStringContainsString('Eve Black', $result);
    }

    #[Test]
    public function it_formats_login_log_in_first_person(): void
    {
        $log = $this->makeLog([
            'type' => 'login',
            'message' => null,
            'options' => ['ip' => '127.0.0.1', 'device' => 'Chrome'],
        ]);

        $result = $this->formatter->format($log, firstPerson: true);

        $this->assertStringContainsString('You', $result);
        $this->assertStringContainsString('127.0.0.1', $result);
        $this->assertStringContainsString('Chrome', $result);
    }

    #[Test]
    public function it_formats_login_log_in_third_person(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Frank', 'last_name' => 'Lee']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        $log = $this->makeLog(
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
    }

    #[Test]
    public function it_uses_unknown_for_missing_ip_and_device_in_login_log(): void
    {
        $log = $this->makeLog([
            'type' => 'login',
            'message' => null,
            'options' => [],
        ]);

        $result = $this->formatter->format($log, firstPerson: true);

        $this->assertStringContainsString('unknown', $result);
    }

    #[Test]
    public function it_returns_raw_message_for_unknown_log_type(): void
    {
        $log = $this->makeLog([
            'type' => 'custom-event',
            'message' => 'Something custom happened',
        ]);

        $result = $this->formatter->format($log);

        $this->assertSame('Something custom happened', $result);
    }

    #[Test]
    public function it_handles_null_logable_gracefully(): void
    {
        $log = $this->makeLog([
            'type' => 'created',
            'message' => null,
        ]);

        // No logable set — getResource() returns null type
        $result = $this->formatter->format($log);

        // Should not throw; admin falls back to "System"
        $this->assertIsString($result);
        $this->assertStringContainsString('System', $result);
    }

    #[Test]
    public function it_handles_null_admin_gracefully(): void
    {
        $admin = Admin::factory()->make(['first_name' => 'Gina', 'last_name' => 'Hall']);

        Logable::add(Admin::class, fn ($model) => ['name' => $model->name]);

        // No admin relation set
        $log = $this->makeLog(
            ['type' => 'deleted', 'message' => null],
            $admin,
        );

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('System', $result);
    }

    #[Test]
    public function it_falls_back_to_type_as_resource_name_when_no_mapper_registered(): void
    {
        // A model with no registered Logable mapper produces name = null.
        // The formatter falls back to $resource['type'] (e.g. "Admin") as the resource name.
        $admin = Admin::factory()->make(['first_name' => 'Henry', 'last_name' => 'Ford']);

        // Reset the Admin mapper so name stays null for this test.
        $reflection = new \ReflectionClass(Logable::class);
        $prop = $reflection->getProperty('mappers');
        $prop->setAccessible(true);
        $mappers = $prop->getValue();
        unset($mappers[Admin::class]);
        $prop->setValue(null, $mappers);

        $log = $this->makeLog(
            ['type' => 'updated', 'message' => null],
            $admin,
        );

        $result = $this->formatter->format($log);

        // No mapper → name is null → formatter falls back to type ("Admin") as resource name
        $this->assertStringContainsString('Admin', $result);
        $this->assertStringContainsString('updated', $result);
    }

    #[Test]
    public function it_falls_back_to_record_when_both_type_and_name_are_null(): void
    {
        // No logable at all → Logable::resolve() returns type=null, name=null
        // → formatter substitutes "Record"
        $log = $this->makeLog(['type' => 'created', 'message' => null]);
        // No logable relation set → getResource() returns ['type' => null, 'name' => null, ...]

        $result = $this->formatter->format($log);

        $this->assertStringContainsString('Record', $result);
    }
}
