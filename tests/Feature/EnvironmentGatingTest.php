<?php

namespace IgcLabs\Floop\Tests\Feature;

use IgcLabs\Floop\Tests\TestCase;

/**
 * Tests that Floop's HTTP surface (routes, middleware, widget) is completely
 * disabled when the current environment is not in the allowed list.
 *
 * This is a separate test class because the environment gate runs at boot
 * time in the service provider — existing tests set environments to ['testing']
 * so they can exercise the full feature set.
 */
class EnvironmentGatingTest extends TestCase
{
    /**
     * Override the base class to set allowed environments to ['local']
     * while Orchestra Testbench runs in the 'testing' environment.
     * This triggers the env gate in FloopServiceProvider::boot().
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('floop.storage_path', $this->tempStoragePath);
        $app['config']->set('floop.environments', ['local']);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    public function test_feedback_routes_return_404_when_environment_not_allowed(): void
    {
        $this->postJson('/_feedback', ['message' => 'test'])->assertNotFound();
        $this->getJson('/_feedback')->assertNotFound();
        $this->getJson('/_feedback/counts')->assertNotFound();
    }

    public function test_widget_is_not_injected_when_environment_not_allowed(): void
    {
        \Illuminate\Support\Facades\Route::get('/gating-test', function () {
            return response('<html><body><h1>Hello</h1></body></html>');
        })->middleware('web');

        $response = $this->get('/gating-test');

        $response->assertOk();
        $this->assertStringNotContainsString('floop-widget', $response->getContent());
    }
}
