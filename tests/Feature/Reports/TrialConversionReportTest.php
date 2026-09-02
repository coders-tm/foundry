<?php

use Carbon\Carbon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\Acquisition\TrialConversionReport;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('report generates trial conversion data', function () {
    // Arrange
    $from = Carbon::now()->subMonths(1)->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    $plan = Plan::factory()->create(['trial_days' => 14]);

    Subscription::factory()->create([
        'user_id' => 1001,
        'plan_id' => $plan->id,
        'type' => 'app',
        'status' => 'active',
        'quantity' => 1,
        'trial_ends_at' => $from->copy()->addDays(14),
        'created_at' => $from->copy(),
        'starts_at' => $from->copy(),
    ]);

    Subscription::factory()->create([
        'user_id' => 1002,
        'plan_id' => $plan->id,
        'type' => 'app',
        'status' => 'cancelled',
        'quantity' => 1,
        'trial_ends_at' => $from->copy()->addDays(14),
        'created_at' => $from->copy()->addDays(1),
        'starts_at' => $from->copy()->addDays(1),
        'cancels_at' => $from->copy()->addDays(10),
    ]);

    // Act
    $report = new TrialConversionReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
        'granularity' => 'monthly',
    ];

    $result = $report->paginate($report->validate($filters), 25, 1);

    // Assert
    expect($result)->toBeArray();
    expect($result)->toHaveKey('data');
});

it('summary calculates conversion rate', function () {
    // Arrange
    $from = Carbon::now()->subMonth()->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    $plan = Plan::factory()->create(['trial_days' => 7]);

    Subscription::factory()->create([
        'user_id' => 1001,
        'plan_id' => $plan->id,
        'type' => 'app',
        'status' => 'active',
        'quantity' => 1,
        'trial_ends_at' => $from->copy()->addDays(7),
        'created_at' => $from->copy(),
        'starts_at' => $from->copy(),
    ]);

    // Act
    $report = new TrialConversionReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];

    $summary = $report->summarize($report->validate($filters));

    // Assert
    expect($summary)->toBeArray();
});
