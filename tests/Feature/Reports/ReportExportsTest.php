<?php

use App\Models\User;
use Foundry\Jobs\GenerateReport;
use Foundry\Models\ReportExport;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(FeatureTestCase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->admin = Foundry\Models\Admin::factory()->create();
    $this->actingAs($this->admin, 'admin');
});

it('admin can get available reports', function () {
    $response = $this->getJson('/admin/reports/exports/available');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'reports' => [
                'revenue',
                'retention',
                'economics',
                'acquisition',
                'orders',
                'exports',
            ],
            'categories',
        ]);

    $data = $response->json();

    // Verify each category has reports with value and label
    foreach ($data['reports'] as $category => $reports) {
        $this->assertIsArray($reports);
        foreach ($reports as $report) {
            $this->assertArrayHasKey('value', $report);
            $this->assertArrayHasKey('label', $report);
        }
    }

    // Verify categories have labels
    $this->assertArrayHasKey('revenue', $data['categories']);
    $this->assertArrayHasKey('exports', $data['categories']);
});

it('admin can get report metadata', function () {
    $response = $this->getJson('/admin/reports/exports/metadata?type=users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'type',
            'label',
            'description',
            'fields' => [
                '*' => ['value', 'label'],
            ],
            'category',
        ]);

    $data = $response->json();

    $this->assertEquals('users', $data['type']);
    $this->assertEquals('exports', $data['category']);
    $this->assertNotEmpty($data['description']);
    $this->assertIsArray($data['fields']);
    $this->assertNotEmpty($data['fields']);
});

it('admin cannot get metadata for invalid report type', function () {
    $response = $this->getJson('/admin/reports/exports/metadata?type=invalid-type');

    $response->assertStatus(422);
});

it('admin can list their report exports', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    ReportExport::factory()->count(3)->create(['admin_id' => $this->admin->id]);
    ReportExport::factory()->count(2)->create(); // Other admin's exports

    $response = $this->getJson('/admin/reports/exports/');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('admin can filter exports by type', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    ReportExport::factory()->create(['admin_id' => $this->admin->id, 'type' => 'subscriptions']);
    ReportExport::factory()->create(['admin_id' => $this->admin->id, 'type' => 'orders']);

    $response = $this->getJson('/admin/reports/exports/?type=subscriptions');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'subscriptions');
});

it('admin can filter exports by payments type', function () {
    ReportExport::factory()->count(2)->create([
        'admin_id' => $this->admin->id,
        'type' => 'payments',
    ]);
    ReportExport::factory()->create([
        'admin_id' => $this->admin->id,
        'type' => 'orders',
    ]);

    $response = $this->getJson('/admin/reports/exports/?type=payments');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $data = $response->json('data');
    $this->assertTrue(collect($data)->every(fn ($item) => $item['type'] === 'payments'));
});

it('admin can filter exports by status', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    ReportExport::factory()->create(['admin_id' => $this->admin->id, 'status' => 'completed']);
    ReportExport::factory()->create(['admin_id' => $this->admin->id, 'status' => 'pending']);

    $response = $this->getJson('/admin/reports/exports?status=completed');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'completed');
});

it('admin can view specific export', function () {
    $admin = Foundry\Models\Admin::factory()->create();
    $export = ReportExport::factory()->create(['admin_id' => $this->admin->id]);

    $response = $this->getJson("/admin/reports/exports/{$export->id}");

    $response->assertStatus(200)
        ->assertJsonPath('id', $export->id);
});

it('admin cannot view another admins export', function () {
    /** @var Admin $admin1 */
    $admin1 = Foundry\Models\Admin::factory()->create();
    $admin2 = Foundry\Models\Admin::factory()->create();
    $this->actingAs($admin1);

    $export = ReportExport::factory()->create(['admin_id' => $admin2->id]);

    $response = $this->getJson("/admin/reports/exports/{$export->id}");

    $response->assertStatus(403);
});

it('admin can download completed export', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    $export = ReportExport::factory()->create([
        'admin_id' => $this->admin->id,
        'status' => 'completed',
        'file_path' => 'reports/test.csv',
        'file_name' => 'test.csv',
    ]);

    Storage::put('reports/test.csv', 'test,data');

    $response = $this->getJson("/admin/reports/exports/{$export->id}/download");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Download link generated successfully.')
        ->assertJsonStructure(['url', 'name', 'expires_at']);
});

it('admin cannot download pending export', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    $export = ReportExport::factory()->create([
        'admin_id' => $this->admin->id,
        'status' => 'pending',
    ]);

    $response = $this->getJson("/admin/reports/exports/{$export->id}/download");

    $response->assertStatus(400)
        ->assertJson(['message' => 'Report is not ready for download yet.']);
});

it('admin can delete their export', function () {
    $admin = Foundry\Models\Admin::factory()->create();
    $export = ReportExport::factory()->create(['admin_id' => $this->admin->id]);

    $response = $this->deleteJson("/admin/reports/exports/{$export->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
});

it('admin can delete multiple exports', function () {
    $admin = Foundry\Models\Admin::factory()->create();
    $exports = ReportExport::factory()->count(3)->create(['admin_id' => $this->admin->id]);

    $response = $this->deleteJson('/admin/reports/exports/destroy', [
        'ids' => $exports->pluck('id')->toArray(),
    ]);

    $response->assertStatus(200);
    $this->assertEquals(0, ReportExport::count());
});

it('admin can retry failed export', function () {
    Queue::fake();

    $admin = Foundry\Models\Admin::factory()->create();
    $export = ReportExport::factory()->create([
        'admin_id' => $this->admin->id,
        'status' => 'failed',
        'error_message' => 'Test error',
    ]);

    $response = $this->postJson("/admin/reports/exports/{$export->id}/retry");

    $response->assertStatus(200);

    $export->refresh();
    $this->assertEquals('pending', $export->status);
    $this->assertNull($export->error_message);

    Queue::assertPushed(GenerateReport::class);
});

it('cannot retry non failed export', function () {
    $admin = Foundry\Models\Admin::factory()->create();
    $export = ReportExport::factory()->create([
        'admin_id' => $this->admin->id,
        'status' => 'completed',
    ]);

    $response = $this->postJson("/admin/reports/exports/{$export->id}/retry");

    $response->assertStatus(400)
        ->assertJson(['message' => 'Only failed reports can be retried.']);
});

it('admin can cleanup expired reports', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    // Create expired reports (completed over 30 days ago)
    ReportExport::factory()->count(2)->create([
        'admin_id' => $this->admin->id,
        'status' => 'completed',
        'completed_at' => now()->subDays(31),
        'expires_at' => now()->subDays(1),
    ]);

    // Create recent reports
    ReportExport::factory()->count(3)->create([
        'admin_id' => $this->admin->id,
        'status' => 'completed',
        'completed_at' => now()->subDays(5),
        'expires_at' => now()->addDays(5),
    ]);

    $response = $this->postJson('/admin/reports/exports/cleanup');

    $response->assertStatus(200);
    // Should have 3 remaining (recent ones)
    $this->assertEquals(3, ReportExport::count());
});

it('generate report job processes subscriptions', function () {
    $admin = Foundry\Models\Admin::factory()->create();
    $users = User::factory()->count(5)->create();
    $plan = Plan::factory()->create();
    $users->map(function ($user) use ($plan) {
        return Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    });

    $export = ReportExport::create([
        'admin_id' => $this->admin->id,
        'type' => 'subscriptions',
        'status' => 'pending',
        'file_name' => 'test.csv',
        'filters' => [
            'format' => 'csv',
            'fields' => [],
        ],
    ]);

    $job = new GenerateReport($export);
    $job->handle();

    $export->refresh();
    $this->assertEquals('completed', $export->status);
    $this->assertNotNull($export->file_path);
    $this->assertEquals(5, $export->total_records);
    $this->assertTrue(Storage::exists($export->file_path));
});

it('generate report job marks as failed on error', function () {
    $admin = Foundry\Models\Admin::factory()->create();

    $export = ReportExport::create([
        'admin_id' => $this->admin->id,
        'type' => 'invalid_type',
        'status' => 'pending',
        'file_name' => 'test.csv',
    ]);

    $job = new GenerateReport($export);

    try {
        $job->handle();
    } catch (Throwable $e) {
        // Expected to throw
    }

    $export->refresh();
    $this->assertEquals('failed', $export->status);
    $this->assertNotNull($export->error_message);
});

it('report export deletes file when deleted', function () {
    $export = ReportExport::factory()->create([
        'file_path' => 'reports/test.csv',
    ]);

    Storage::put('reports/test.csv', 'test,data');
    $this->assertTrue(Storage::exists('reports/test.csv'));

    $export->delete();

    $this->assertFalse(Storage::exists('reports/test.csv'));
});
