<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.test');
$dotenv->load();

if (file_exists(__DIR__ . '/../.env.test.local')) {
    $local = Dotenv\Dotenv::createMutable(__DIR__ . '/..', '.env.test.local');
    $local->load();
}
