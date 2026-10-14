<?php
declare(strict_types=1);

namespace Bulbula\Core;

/**
 * Runtime OPcache & Stat-Cache Synchronizer for Shared Hosting.
 *
 * Shared hosting frequently enables OPcache with `opcache.validate_timestamps=0`
 * or long revalidation intervals, trapping developers into needing a server restart
 * they do not have permission to run.
 *
 * This class checks `.php` file modification timestamps in user space and calls
 * `opcache_invalidate($file, true)` + `clearstatcache(true)` directly from PHP code.
 */
final class LiveReload
{
    public static function autoRefreshModifiedScripts(string $rootDir): void
    {
        clearstatcache(true);

        if (!function_exists('opcache_invalidate')) {
            return;
        }

        $stateFile = $rootDir . '/storage/cache/_opcache_mtime.json';
        $lastCheck = is_file($stateFile) ? (int) @filemtime($stateFile) : 0;

        // Check at most once every 2 seconds to keep overhead near 0.1ms
        if ((time() - $lastCheck) < 2) {
            return;
        }

        $dirs = [
            $rootDir . '/config',
            $rootDir . '/src',
            $rootDir . '/public_html',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                    if ($fileInfo->getMTime() >= $lastCheck) {
                        @opcache_invalidate($fileInfo->getRealPath(), true);
                    }
                }
            }
        }

        @file_put_contents($stateFile, (string) time());
    }

    /**
     * Force-invalidate every PHP file in the project + reset OPcache if permitted.
     */
    public static function forceFlushAll(string $rootDir): int
    {
        clearstatcache(true);
        $count = 0;

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        if (!function_exists('opcache_invalidate')) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                if (@opcache_invalidate($fileInfo->getRealPath(), true)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
