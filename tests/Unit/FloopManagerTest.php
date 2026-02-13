<?php

namespace IgcLabs\Floop\Tests\Unit;

use IgcLabs\Floop\Tests\TestCase;

class FloopManagerTest extends TestCase
{
    // ── store() ─────────────────────────────────────────────

    public function test_store_creates_markdown_file_in_pending_directory(): void
    {
        $manager = $this->makeManager();

        $manager->store(['message' => 'Test feedback', 'type' => 'feedback']);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $this->assertCount(1, $files);
    }

    public function test_store_returns_filename_with_expected_format(): void
    {
        $manager = $this->makeManager();

        $filename = $manager->store(['message' => 'My feedback message', 'type' => 'bug']);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}_\d{6}_[\w-]+\.md$/',
            $filename
        );
    }

    public function test_store_includes_message_and_metadata_in_markdown(): void
    {
        $manager = $this->makeManager();

        $manager->store(['message' => 'Button is broken', 'type' => 'bug', 'priority' => 'high']);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('# ', $content);
        $this->assertStringContainsString('**Status:**', $content);
        $this->assertStringContainsString('**Type:** Bug', $content);
        $this->assertStringContainsString('Button is broken', $content);
        $this->assertStringContainsString('**Priority:**', $content);
    }

    public function test_store_includes_context_when_provided(): void
    {
        $manager = $this->makeManager();

        $manager->store([
            'message' => 'Needs improvement',
            'type' => 'feedback',
            'url' => 'https://example.com/dashboard',
            'route_name' => 'dashboard.index',
            'route_action' => 'DashboardController@index',
        ]);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('## Page Context', $content);
        $this->assertStringContainsString('https://example.com/dashboard', $content);
        $this->assertStringContainsString('dashboard.index', $content);
        $this->assertStringContainsString('DashboardController@index', $content);
    }

    // ── markActioned() ──────────────────────────────────────

    public function test_mark_actioned_moves_file_from_pending_to_actioned(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Fix this', 'type' => 'bug']);

        $result = $manager->markActioned($filename);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
        $this->assertFileExists($this->tempStoragePath.'/actioned/'.$filename);
    }

    public function test_mark_actioned_updates_status_in_content(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Fix this', 'type' => 'bug']);

        $manager->markActioned($filename);

        $content = file_get_contents($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertStringContainsString('Actioned', $content);
        $this->assertStringNotContainsString('Pending', $content);
    }

    public function test_mark_actioned_returns_false_for_nonexistent_file(): void
    {
        $manager = $this->makeManager();

        $result = $manager->markActioned('nonexistent.md');

        $this->assertFalse($result);
    }

    // ── markPending() ───────────────────────────────────────

    public function test_mark_pending_moves_file_from_actioned_to_pending(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Reopen this', 'type' => 'task']);
        $manager->markActioned($filename);

        $result = $manager->markPending($filename);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertFileExists($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_mark_pending_updates_status_in_content(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Reopen this', 'type' => 'task']);
        $manager->markActioned($filename);

        $manager->markPending($filename);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('Pending', $content);
        $this->assertStringNotContainsString('Actioned', $content);
    }

    public function test_mark_pending_returns_false_for_nonexistent_file(): void
    {
        $manager = $this->makeManager();

        $result = $manager->markPending('nonexistent.md');

        $this->assertFalse($result);
    }

    // ── delete() ────────────────────────────────────────────

    public function test_delete_removes_pending_file(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Delete me', 'type' => 'feedback']);

        $result = $manager->delete($filename, 'pending');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_delete_removes_actioned_file(): void
    {
        $manager = $this->makeManager();
        $filename = $manager->store(['message' => 'Delete me too', 'type' => 'feedback']);
        $manager->markActioned($filename);

        $result = $manager->delete($filename, 'actioned');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/actioned/'.$filename);
    }

    public function test_delete_returns_false_for_nonexistent_file(): void
    {
        $manager = $this->makeManager();

        $result = $manager->delete('nonexistent.md', 'pending');

        $this->assertFalse($result);
    }
}
