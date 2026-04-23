<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Admin;
use Foundry\Models\ReportExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ReportExport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'type' => fake()->randomElement(['subscriptions', 'orders', 'customers']),
            'status' => 'pending',
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'total_records' => null,
            'filters' => null,
            'metadata' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the export is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'file_name' => 'export_'.now()->format('Y-m-d_His').'.csv',
            'file_path' => 'reports/export_'.now()->format('Y-m-d_His').'.csv',
            'file_size' => fake()->numberBetween(1000, 1000000),
            'total_records' => fake()->numberBetween(10, 10000),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate that the export is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now()->subMinutes(2),
        ]);
    }

    /**
     * Indicate that the export has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'An error occurred during export generation',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the export is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'file_name' => 'old_export.csv',
            'file_path' => 'reports/old_export.csv',
            'file_size' => 5000,
            'total_records' => 100,
            'started_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);
    }

    /**
     * Indicate the export type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Add filters to the export.
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => $filters,
        ]);
    }
}
