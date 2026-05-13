<?php

declare(strict_types=1);

/**
 * Run `wayfinder:generate` under an exclusive lock so concurrent Vite hooks
 * (buildStart + handleHotUpdate) cannot interleave and double-prepend imports.
 */
$projectRoot = dirname(__DIR__);
$lockDir = $projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework';
$lockPath = $lockDir.DIRECTORY_SEPARATOR.'wayfinder-generate.lock';

if (! is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}

$lock = fopen($lockPath, 'c');

if ($lock === false) {
    fwrite(STDERR, "Could not open wayfinder lock file.\n");
    exit(1);
}

if (! flock($lock, LOCK_EX)) {
    fwrite(STDERR, "Could not acquire wayfinder lock.\n");
    exit(1);
}

try {
    $forwarded = array_map('escapeshellarg', array_slice($argv, 1));
    $artisan = escapeshellarg($projectRoot.DIRECTORY_SEPARATOR.'artisan');
    $php = escapeshellarg(PHP_BINARY);
    $cmd = $php.' '.$artisan.' wayfinder:generate '.implode(' ', $forwarded);
    passthru($cmd, $exitCode);

    exit($exitCode ?? 0);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
