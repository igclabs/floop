<?php

namespace IgcLabs\Floop\Tests;

use IgcLabs\Floop\FloopManager;
use IgcLabs\Floop\FloopServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $tempStoragePath;

    protected function getPackageProviders($app): array
    {
        return [FloopServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStoragePath = sys_get_temp_dir().'/floop_test_'.uniqid();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempStoragePath);

        parent::tearDown();
    }

    protected function makeManager(): FloopManager
    {
        return new FloopManager($this->tempStoragePath);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.'/'.$item;

            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
