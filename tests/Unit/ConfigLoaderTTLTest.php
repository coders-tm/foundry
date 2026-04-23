<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\ConfigLoader;
use Foundry\Tests\BaseTestCase;
use ReflectionClass;

class ConfigLoaderTTLTest extends BaseTestCase
{
    public function test_cache_ttl_is_24_hours()
    {
        $loader = new ConfigLoader;
        $reflection = new ReflectionClass($loader);

        // CACHE_TTL is private, so we use reflection to access it
        $constants = $reflection->getConstants();

        $this->assertArrayHasKey('CACHE_TTL', $constants);
        $this->assertEquals(86400, $constants['CACHE_TTL'], 'CACHE_TTL should be 86400 seconds (24 hours)');
    }
}
