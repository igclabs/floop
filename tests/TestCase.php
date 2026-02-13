<?php

namespace IgcLabs\Floop\Tests;

use IgcLabs\Floop\FloopManager;
use IgcLabs\Floop\FloopServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $tempStoragePath;

    protected FloopManager $manager;

    protected function getPackageProviders($app): array
    {
        return [FloopServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('floop.storage_path', $this->tempStoragePath);
    }

    protected function setUp(): void
    {
        $this->tempStoragePath = sys_get_temp_dir().'/floop_test_'.uniqid();

        parent::setUp();

        $this->manager = app(FloopManager::class);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempStoragePath);

        parent::tearDown();
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
