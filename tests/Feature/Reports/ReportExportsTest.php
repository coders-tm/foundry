<?php

namespace Foundry\Tests\Feature;

use App\Models\User;
use Foundry\Jobs\GenerateReport;
use Foundry\Models\Admin;
use Foundry\Models\ReportExport;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class ReportExportsTest extends FeatureTestCase
{
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        /** @var Admin $admin */
        $this->admin = $this->createAdmin();
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function admin_can_get_available_reports()
    {
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
    }

    #[Test]
    public function admin_can_get_report_metadata()
    {
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
    }

    #[Test]
    public function admin_cannot_get_metadata_for_invalid_report_type()
    {
        $response = $this->getJson('/admin/reports/exports/metadata?type=invalid-type');

        $response->assertStatus(422);
    }

    #[Test]
    public function admin_can_list_their_report_exports()
    {
        $admin = $this->createAdmin();

        ReportExport::factory()->count(3)->create(['admin_id' => $this->admin->id]);
        ReportExport::factory()->count(2)->create(); // Other admin's exports

        $response = $this->getJson('/admin/reports/exports/');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function admin_can_filter_exports_by_type()
    {
        $admin = $this->createAdmin();

        ReportExport::factory()->create(['admin_id' => $this->admin->id, 'type' => 'subscriptions']);
        ReportExport::factory()->create(['admin_id' => $this->admin->id, 'type' => 'orders']);

        $response = $this->getJson('/admin/reports/exports/?type=subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'subscriptions');
    }

    #[Test]
    public function admin_can_filter_exports_by_payments_type()
    {
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
    }

    #[Test]
    public function admin_can_filter_exports_by_status()
    {
        $admin = $this->createAdmin();

        ReportExport::factory()->create(['admin_id' => $this->admin->id, 'status' => 'completed']);
        ReportExport::factory()->create(['admin_id' => $this->admin->id, 'status' => 'pending']);

        $response = $this->getJson('/admin/reports/exports?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    #[Test]
    public function admin_can_view_specific_export()
    {
        $admin = $this->createAdmin();
        $export = ReportExport::factory()->create(['admin_id' => $this->admin->id]);

        $response = $this->getJson("/admin/reports/exports/{$export->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $export->id);
    }

    #[Test]
    public function admin_cannot_view_another_admins_export()
    {
        /** @var Admin $admin1 */
        $admin1 = $this->createAdmin();
        $admin2 = $this->createAdmin();
        $this->actingAs($admin1);

        $export = ReportExport::factory()->create(['admin_id' => $admin2->id]);

        $response = $this->getJson("/admin/reports/exports/{$export->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_download_completed_export()
    {
        $admin = $this->createAdmin();

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
    }

    #[Test]
    public function admin_cannot_download_pending_export()
    {
        $admin = $this->createAdmin();

        $export = ReportExport::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson("/admin/reports/exports/{$export->id}/download");

        $response->assertStatus(400)
            ->assertJson(['message' => 'Report is not ready for download yet.']);
    }

    #[Test]
    public function admin_can_delete_their_export()
    {
        $admin = $this->createAdmin();
        $export = ReportExport::factory()->create(['admin_id' => $this->admin->id]);

        $response = $this->deleteJson("/admin/reports/exports/{$export->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('report_exports', ['id' => $export->id]);
    }

    #[Test]
    public function admin_can_delete_multiple_exports()
    {
        $admin = $this->createAdmin();
        $exports = ReportExport::factory()->count(3)->create(['admin_id' => $this->admin->id]);

        $response = $this->deleteJson('/admin/reports/exports/destroy', [
            'ids' => $exports->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, ReportExport::count());
    }

    #[Test]
    public function admin_can_retry_failed_export()
    {
        Queue::fake();

        $admin = $this->createAdmin();
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
    }

    #[Test]
    public function cannot_retry_non_failed_export()
    {
        $admin = $this->createAdmin();
        $export = ReportExport::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => 'completed',
        ]);

        $response = $this->postJson("/admin/reports/exports/{$export->id}/retry");

        $response->assertStatus(400)
            ->assertJson(['message' => 'Only failed reports can be retried.']);
    }

    #[Test]
    public function admin_can_cleanup_expired_reports()
    {
        $admin = $this->createAdmin();

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
    }

    #[Test]
    public function generate_report_job_processes_subscriptions()
    {
        $admin = $this->createAdmin();
        $this->createSubscriptions(5);

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
    }

    #[Test]
    public function generate_report_job_marks_as_failed_on_error()
    {
        $admin = $this->createAdmin();

        $export = ReportExport::create([
            'admin_id' => $this->admin->id,
            'type' => 'invalid_type',
            'status' => 'pending',
            'file_name' => 'test.csv',
        ]);

        $job = new GenerateReport($export);

        try {
            $job->handle();
        } catch (\Throwable $e) {
            // Expected to throw
        }

        $export->refresh();
        $this->assertEquals('failed', $export->status);
        $this->assertNotNull($export->error_message);
    }

    #[Test]
    public function report_export_deletes_file_when_deleted()
    {
        $export = ReportExport::factory()->create([
            'file_path' => 'reports/test.csv',
        ]);

        Storage::put('reports/test.csv', 'test,data');
        $this->assertTrue(Storage::exists('reports/test.csv'));

        $export->delete();

        $this->assertFalse(Storage::exists('reports/test.csv'));
    }

    /**
     * Create admin for testing
     */
    protected function createAdmin()
    {
        return Admin::factory()->create();
    }

    /**
     * Create subscriptions for testing
     */
    protected function createSubscriptions(int $count = 1)
    {
        $userModel = User::class;
        $users = $userModel::factory()->count($count)->create();

        $plan = Plan::factory()->create();

        return $users->map(function ($user) use ($plan) {
            return Subscription::factory()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);
        });
    }
}
