<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\ConfigLoader;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseOptimizerTest extends BaseTestCase
{
    public function test_optimize_response_adds_headers()
    {
        $loader = new ConfigLoader;
        $request = Request::create('/', 'GET');
        $response = new Response('<html><body><h1>Hello</h1></body></html>', 200, ['Content-Type' => 'text/html']);

        $optimizedResponse = $loader->optimizeResponse($request, $response);

        $this->assertTrue($optimizedResponse->headers->has('X-Product-Owner'));
        $this->assertTrue($optimizedResponse->headers->has('X-Product-Id'));
        $this->assertTrue($optimizedResponse->headers->has('X-App-Id'));
        $this->assertTrue($optimizedResponse->headers->has('X-Legal-Notice'));
        $this->assertTrue($optimizedResponse->headers->has('X-License-Status'));
    }

    public function test_optimize_response_injects_script_for_html()
    {
        $loader = new ConfigLoader;
        $request = Request::create('/', 'GET');
        $content = '<html><head><title>Test</title></head><body><h1>Hello</h1></body></html>';
        $response = new Response($content, 200, ['Content-Type' => 'text/html']);

        $optimizedResponse = $loader->optimizeResponse($request, $response);
        $newContent = $optimizedResponse->getContent();

        $this->assertStringContainsString('<meta name="product-owner"', $newContent);
    }

    public function test_optimize_response_does_not_inject_duplicate_meta_tags()
    {
        $loader = new ConfigLoader;
        $request = Request::create('/', 'GET');
        $content = '<html><head><title>Test</title></head><body><h1>Hello</h1></body></html>';
        $response = new Response($content, 200, ['Content-Type' => 'text/html']);

        // First injection
        $optimizedResponse = $loader->optimizeResponse($request, $response);
        $contentAfterFirst = $optimizedResponse->getContent();

        // Second injection
        $optimizedResponse = $loader->optimizeResponse($request, $optimizedResponse);
        $contentAfterSecond = $optimizedResponse->getContent();

        $this->assertEquals($contentAfterFirst, $contentAfterSecond);
        $this->assertEquals(1, substr_count($contentAfterSecond, '<meta name="product-id"'));
    }
}
