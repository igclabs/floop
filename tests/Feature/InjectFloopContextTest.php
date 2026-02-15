<?php

namespace IgcLabs\Floop\Tests\Feature;

use IgcLabs\Floop\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class InjectFloopContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-page', function () {
            return response('<html><body><h1>Hello</h1></body></html>');
        })->middleware('web');
    }

    public function test_widget_is_injected_into_html_responses(): void
    {
        $response = $this->get('/test-page');

        $response->assertOk();
        $this->assertStringContainsString('id="floop-widget"', $response->getContent());
    }

    public function test_widget_is_not_injected_into_json_responses(): void
    {
        Route::get('/test-api', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->getJson('/test-api');

        $response->assertOk();
        $this->assertStringNotContainsString('floop-widget', $response->getContent());
    }

    public function test_widget_is_not_injected_when_disabled(): void
    {
        $this->manager->disable();

        $response = $this->get('/test-page');

        $response->assertOk();
        $this->assertStringNotContainsString('floop-widget', $response->getContent());
    }

    public function test_widget_is_not_injected_when_auto_inject_is_false(): void
    {
        config(['floop.auto_inject' => false]);

        $response = $this->get('/test-page');

        $response->assertOk();
        $this->assertStringNotContainsString('floop-widget', $response->getContent());
    }

    public function test_widget_is_not_duplicated_when_already_present(): void
    {
        Route::get('/test-manual', function () {
            return response('<html><body><div id="floop-widget">manual</div></body></html>');
        })->middleware('web');

        $response = $this->get('/test-manual');

        $response->assertOk();
        // Should appear exactly once (the existing one, not doubled)
        $this->assertEquals(1, substr_count($response->getContent(), 'id="floop-widget"'));
    }

    public function test_widget_is_not_injected_on_redirects(): void
    {
        Route::get('/test-redirect', function () {
            return redirect('/test-page');
        })->middleware('web');

        $response = $this->get('/test-redirect');

        $response->assertRedirect();
    }
}
