<?php

namespace IgcLabs\Floop\Tests\Unit;

use IgcLabs\Floop\Tests\TestCase;

class FloopManagerTest extends TestCase
{
    // ── store() ─────────────────────────────────────────────

    public function test_store_creates_markdown_file_in_pending_directory(): void
    {
        $this->manager->store(['message' => 'Test feedback', 'type' => 'feedback']);

        $files = glob($this->tempStoragePath.'/pending/*.md');
        $this->assertCount(1, $files);
    }

    public function test_store_returns_filename_with_expected_format(): void
    {
        $filename = $this->manager->store(['message' => 'My feedback message', 'type' => 'bug']);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}_\d{6}_[\w-]+\.md$/',
            $filename
        );
    }

    public function test_store_includes_message_and_metadata_in_markdown(): void
    {
        $this->manager->store(['message' => 'Button is broken', 'type' => 'bug', 'priority' => 'high']);

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
        $this->manager->store([
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
        $filename = $this->manager->store(['message' => 'Fix this', 'type' => 'bug']);

        $result = $this->manager->markActioned($filename);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
        $this->assertFileExists($this->tempStoragePath.'/actioned/'.$filename);
    }

    public function test_mark_actioned_updates_status_in_content(): void
    {
        $filename = $this->manager->store(['message' => 'Fix this', 'type' => 'bug']);

        $this->manager->markActioned($filename);

        $content = file_get_contents($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertStringContainsString('Actioned', $content);
        $this->assertStringNotContainsString('Pending', $content);
    }

    public function test_mark_actioned_with_note_appends_agent_notes_section(): void
    {
        $filename = $this->manager->store(['message' => 'Fix the margin', 'type' => 'bug']);

        $this->manager->markActioned($filename, 'Adjusted the margin from 20px to 8px');

        $content = file_get_contents($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertStringContainsString('## Agent Notes', $content);
        $this->assertStringContainsString('Adjusted the margin from 20px to 8px', $content);
    }

    public function test_mark_actioned_without_note_omits_agent_notes_section(): void
    {
        $filename = $this->manager->store(['message' => 'Fix the margin', 'type' => 'bug']);

        $this->manager->markActioned($filename);

        $content = file_get_contents($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertStringNotContainsString('## Agent Notes', $content);
    }

    public function test_mark_actioned_returns_false_for_nonexistent_file(): void
    {
        $result = $this->manager->markActioned('nonexistent.md');

        $this->assertFalse($result);
    }

    // ── markPending() ───────────────────────────────────────

    public function test_mark_pending_moves_file_from_actioned_to_pending(): void
    {
        $filename = $this->manager->store(['message' => 'Reopen this', 'type' => 'task']);
        $this->manager->markActioned($filename);

        $result = $this->manager->markPending($filename);

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/actioned/'.$filename);
        $this->assertFileExists($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_mark_pending_updates_status_in_content(): void
    {
        $filename = $this->manager->store(['message' => 'Reopen this', 'type' => 'task']);
        $this->manager->markActioned($filename);

        $this->manager->markPending($filename);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('Pending', $content);
        $this->assertStringNotContainsString('Actioned', $content);
    }

    public function test_mark_pending_returns_false_for_nonexistent_file(): void
    {
        $result = $this->manager->markPending('nonexistent.md');

        $this->assertFalse($result);
    }

    // ── delete() ────────────────────────────────────────────

    public function test_delete_removes_pending_file(): void
    {
        $filename = $this->manager->store(['message' => 'Delete me', 'type' => 'feedback']);

        $result = $this->manager->delete($filename, 'pending');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
    }

    public function test_delete_removes_actioned_file(): void
    {
        $filename = $this->manager->store(['message' => 'Delete me too', 'type' => 'feedback']);
        $this->manager->markActioned($filename);

        $result = $this->manager->delete($filename, 'actioned');

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/actioned/'.$filename);
    }

    public function test_delete_returns_false_for_nonexistent_file(): void
    {
        $result = $this->manager->delete('nonexistent.md', 'pending');

        $this->assertFalse($result);
    }

    // ── all() ──────────────────────────────────────────────

    public function test_all_returns_pending_and_actioned_arrays(): void
    {
        $this->manager->store(['message' => 'Pending item', 'type' => 'feedback']);
        $filename = $this->manager->store(['message' => 'Actioned item', 'type' => 'bug']);
        $this->manager->markActioned($filename);

        $all = $this->manager->all();

        $this->assertCount(1, $all['pending']);
        $this->assertCount(1, $all['actioned']);
        $this->assertStringContainsString('Pending item', $all['pending'][0]['title']);
    }

    public function test_all_returns_empty_arrays_when_no_items(): void
    {
        $all = $this->manager->all();

        $this->assertSame([], $all['pending']);
        $this->assertSame([], $all['actioned']);
    }

    // ── counts() ───────────────────────────────────────────

    public function test_counts_returns_correct_totals(): void
    {
        $this->manager->store(['message' => 'One', 'type' => 'feedback']);
        $this->manager->store(['message' => 'Two', 'type' => 'feedback']);
        $filename = $this->manager->store(['message' => 'Three', 'type' => 'bug']);
        $this->manager->markActioned($filename);

        $counts = $this->manager->counts();

        $this->assertSame(2, $counts['pending']);
        $this->assertSame(1, $counts['actioned']);
    }

    // ── enable() / disable() / isEnabled() ─────────────────

    public function test_disable_creates_flag_file_and_reports_disabled(): void
    {
        $this->assertTrue($this->manager->isEnabled());

        $this->manager->disable();

        $this->assertFalse($this->manager->isEnabled());
        $this->assertFileExists($this->tempStoragePath.'/.disabled');
    }

    public function test_enable_removes_flag_file_and_reports_enabled(): void
    {
        $this->manager->disable();
        $this->assertFalse($this->manager->isEnabled());

        $this->manager->enable();

        $this->assertTrue($this->manager->isEnabled());
        $this->assertFileDoesNotExist($this->tempStoragePath.'/.disabled');
    }

    // ── screenshot support ─────────────────────────────────

    public function test_store_saves_screenshot_as_png_file(): void
    {
        $filename = $this->manager->store([
            'message' => 'Layout broken',
            'type' => 'bug',
            'screenshot' => 'data:image/png;base64,'.base64_encode('fake-png-data'),
        ]);

        $pngFilename = preg_replace('/\.md$/', '.png', $filename);
        $this->assertFileExists($this->tempStoragePath.'/pending/'.$pngFilename);
        $this->assertSame('fake-png-data', file_get_contents($this->tempStoragePath.'/pending/'.$pngFilename));
    }

    public function test_store_includes_screenshot_reference_in_markdown(): void
    {
        $filename = $this->manager->store([
            'message' => 'Button misaligned',
            'type' => 'feedback',
            'screenshot' => 'data:image/png;base64,'.base64_encode('fake-png-data'),
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('## Screenshot', $content);
        $this->assertStringContainsString('![Screenshot]', $content);
    }

    public function test_mark_actioned_moves_companion_screenshot(): void
    {
        $filename = $this->manager->store([
            'message' => 'Move screenshot too',
            'type' => 'bug',
            'screenshot' => 'data:image/png;base64,'.base64_encode('png-bytes'),
        ]);

        $pngFilename = preg_replace('/\.md$/', '.png', $filename);
        $this->manager->markActioned($filename);

        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$pngFilename);
        $this->assertFileExists($this->tempStoragePath.'/actioned/'.$pngFilename);
    }

    public function test_mark_pending_moves_companion_screenshot(): void
    {
        $filename = $this->manager->store([
            'message' => 'Reopen with screenshot',
            'type' => 'task',
            'screenshot' => 'data:image/png;base64,'.base64_encode('png-bytes'),
        ]);

        $pngFilename = preg_replace('/\.md$/', '.png', $filename);
        $this->manager->markActioned($filename);
        $this->manager->markPending($filename);

        $this->assertFileDoesNotExist($this->tempStoragePath.'/actioned/'.$pngFilename);
        $this->assertFileExists($this->tempStoragePath.'/pending/'.$pngFilename);
    }

    public function test_delete_removes_companion_screenshot(): void
    {
        $filename = $this->manager->store([
            'message' => 'Delete with screenshot',
            'type' => 'feedback',
            'screenshot' => 'data:image/png;base64,'.base64_encode('png-bytes'),
        ]);

        $pngFilename = preg_replace('/\.md$/', '.png', $filename);
        $this->manager->delete($filename, 'pending');

        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$filename);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$pngFilename);
    }

    // ── console errors & network failures ───────────────

    public function test_store_includes_console_errors_in_markdown(): void
    {
        $filename = $this->manager->store([
            'message' => 'Page is broken',
            'type' => 'bug',
            'console_errors' => [
                ['message' => "TypeError: Cannot read property 'foo'", 'timestamp' => '14:30:22'],
                ['message' => 'Unhandled Promise Rejection: fetch failed', 'timestamp' => '14:30:25'],
            ],
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('## Console Errors', $content);
        $this->assertStringContainsString("[14:30:22] TypeError: Cannot read property 'foo'", $content);
        $this->assertStringContainsString('[14:30:25] Unhandled Promise Rejection: fetch failed', $content);
    }

    public function test_store_includes_network_failures_in_markdown(): void
    {
        $filename = $this->manager->store([
            'message' => 'API calls failing',
            'type' => 'bug',
            'network_failures' => [
                ['url' => '/api/users', 'method' => 'GET', 'status' => 500, 'statusText' => 'Internal Server Error', 'timestamp' => '14:30:15'],
            ],
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('## Network Failures', $content);
        $this->assertStringContainsString('/api/users', $content);
        $this->assertStringContainsString('500', $content);
        $this->assertStringContainsString('Internal Server Error', $content);
    }

    public function test_store_without_errors_omits_error_sections(): void
    {
        $filename = $this->manager->store([
            'message' => 'Just a comment',
            'type' => 'feedback',
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringNotContainsString('## Console Errors', $content);
        $this->assertStringNotContainsString('## Network Failures', $content);
    }

    // ── targeted element ───────────────────────────────

    public function test_store_includes_targeted_element_in_markdown(): void
    {
        $filename = $this->manager->store([
            'message' => 'This button is wrong',
            'type' => 'bug',
            'targeted_element' => [
                'selector' => '#main > div.container > button.submit',
                'tagName' => 'BUTTON',
                'textContent' => 'Submit Order',
                'boundingBox' => ['top' => 340, 'left' => 520, 'width' => 240, 'height' => 36],
            ],
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringContainsString('## Targeted Element', $content);
        $this->assertStringContainsString('#main > div.container > button.submit', $content);
        $this->assertStringContainsString('BUTTON', $content);
        $this->assertStringContainsString('Submit Order', $content);
        $this->assertStringContainsString('340, 520 (240×36)', $content);
    }

    public function test_store_without_targeted_element_omits_section(): void
    {
        $filename = $this->manager->store([
            'message' => 'General feedback',
            'type' => 'feedback',
        ]);

        $content = file_get_contents($this->tempStoragePath.'/pending/'.$filename);
        $this->assertStringNotContainsString('## Targeted Element', $content);
    }

    public function test_store_without_screenshot_creates_no_png(): void
    {
        $filename = $this->manager->store([
            'message' => 'No screenshot here',
            'type' => 'feedback',
        ]);

        $pngFilename = preg_replace('/\.md$/', '.png', $filename);
        $this->assertFileDoesNotExist($this->tempStoragePath.'/pending/'.$pngFilename);
    }
}
