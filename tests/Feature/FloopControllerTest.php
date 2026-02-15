<?php

namespace IgcLabs\Floop\Tests\Feature;

use IgcLabs\Floop\Events\FeedbackActioned;
use IgcLabs\Floop\Events\FeedbackStored;
use IgcLabs\Floop\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class FloopControllerTest extends TestCase
{
    // ── POST / (store) ──────────────────────────────────────

    public function test_store_creates_work_order_and_returns_success(): void
    {
        $response = $this->postJson('/_feedback', [
            'message' => 'The heading is too small',
            'type' => 'feedback',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['filename']);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $this->assertCount(1, $files);
    }

    public function test_store_validates_message_is_required(): void
    {
        $response = $this->postJson('/_feedback', [
            'type' => 'bug',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_store_validates_type_must_be_allowed_value(): void
    {
        $response = $this->postJson('/_feedback', [
            'message' => 'Something',
            'type' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_dispatches_feedback_stored_event(): void
    {
        Event::fake([FeedbackStored::class]);

        $this->postJson('/_feedback', [
            'message' => 'Event test',
            'type' => 'bug',
        ]);

        Event::assertDispatched(FeedbackStored::class, function (FeedbackStored $event) {
            return $event->type === 'bug' && $event->message === 'Event test';
        });
    }

    public function test_store_includes_context_headers_in_work_order(): void
    {
        $this->postJson('/_feedback', [
            'message' => 'Context check',
            'type' => 'feedback',
        ], [
            'X-Feedback-URL' => 'https://example.com/dashboard',
            'X-Feedback-Method' => 'GET',
        ]);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('https://example.com/dashboard', $content);
    }

    // ── GET /counts ─────────────────────────────────────────

    public function test_counts_returns_pending_and_actioned_totals(): void
    {
        $this->manager->store(['message' => 'One', 'type' => 'feedback']);
        $filename = $this->manager->store(['message' => 'Two', 'type' => 'feedback']);
        $this->manager->markActioned($filename);

        $response = $this->getJson('/_feedback/counts');

        $response->assertOk()
            ->assertJson(['pending' => 1, 'actioned' => 1]);
    }

    // ── GET / (index) ───────────────────────────────────────

    public function test_index_returns_all_work_orders(): void
    {
        $this->manager->store(['message' => 'First', 'type' => 'bug']);
        $this->manager->store(['message' => 'Second', 'type' => 'idea']);

        $response = $this->getJson('/_feedback');

        $response->assertOk()
            ->assertJsonStructure(['pending', 'actioned'])
            ->assertJsonCount(2, 'pending');
    }

    // ── POST /action ────────────────────────────────────────

    public function test_action_done_moves_to_actioned(): void
    {
        $filename = $this->manager->store(['message' => 'Do it', 'type' => 'task']);

        $response = $this->postJson('/_feedback/action', [
            'filename' => $filename,
            'action' => 'done',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
        $this->assertFileExists($this->tempStoragePath.'/actioned/'.$filename);
    }

    public function test_action_done_dispatches_feedback_actioned_event(): void
    {
        Event::fake([FeedbackActioned::class]);

        $filename = $this->manager->store(['message' => 'Event test', 'type' => 'task']);

        $this->postJson('/_feedback/action', [
            'filename' => $filename,
            'action' => 'done',
        ]);

        Event::assertDispatched(FeedbackActioned::class, function (FeedbackActioned $event) use ($filename) {
            return $event->filename === $filename;
        });
    }

    public function test_action_reopen_moves_back_to_pending(): void
    {
        $filename = $this->manager->store(['message' => 'Reopen me', 'type' => 'bug']);
        $this->manager->markActioned($filename);

        $response = $this->postJson('/_feedback/action', [
            'filename' => $filename,
            'action' => 'reopen',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertFileExists($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_action_delete_removes_file(): void
    {
        $filename = $this->manager->store(['message' => 'Delete me', 'type' => 'feedback']);

        $response = $this->postJson('/_feedback/action', [
            'filename' => $filename,
            'action' => 'delete',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_action_returns_404_for_nonexistent_file(): void
    {
        $response = $this->postJson('/_feedback/action', [
            'filename' => 'nonexistent.md',
            'action' => 'done',
        ]);

        $response->assertNotFound()
            ->assertJson(['success' => false]);
    }

    // ── Screenshot support ──────────────────────────────────

    public function test_store_with_screenshot_saves_png_file(): void
    {
        $response = $this->postJson('/_feedback', [
            'message' => 'Button in wrong place',
            'type' => 'bug',
            'screenshot' => 'data:image/png;base64,'.base64_encode('fake-png-data'),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $pngFiles = glob($this->tempStoragePath.'/pending/*.png');
        $this->assertCount(1, $pngFiles);
        $this->assertSame('fake-png-data', file_get_contents($pngFiles[0]));
    }

    // ── Console errors & network failures ─────────────────

    public function test_store_with_console_errors_includes_them_in_work_order(): void
    {
        $response = $this->postJson('/_feedback', [
            'message' => 'Page is broken',
            'type' => 'bug',
            'console_errors' => [
                ['message' => "TypeError: Cannot read property 'foo'", 'timestamp' => '14:30:22'],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('## Console Errors', $content);
        $this->assertStringContainsString("TypeError: Cannot read property 'foo'", $content);
    }

    public function test_store_with_network_failures_includes_them_in_work_order(): void
    {
        $response = $this->postJson('/_feedback', [
            'message' => 'API calls failing',
            'type' => 'bug',
            'network_failures' => [
                ['url' => '/api/users', 'method' => 'GET', 'status' => 500, 'statusText' => 'Internal Server Error', 'timestamp' => '14:30:15'],
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('## Network Failures', $content);
        $this->assertStringContainsString('/api/users', $content);
        $this->assertStringContainsString('500', $content);
    }

    public function test_store_rejects_oversized_screenshot(): void
    {
        config()->set('floop.screenshot_max_size', 100);

        $response = $this->postJson('/_feedback', [
            'message' => 'Oversized screenshot',
            'type' => 'bug',
            'screenshot' => str_repeat('x', 101),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('screenshot');
    }
}
