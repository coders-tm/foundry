<?php

namespace Foundry\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\Database\Seeders\DatabaseSeeder;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }
}
