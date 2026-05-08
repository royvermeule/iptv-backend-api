<?php

declare(strict_types=1);

use App\Kernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require_once __DIR__ . '/vendor/autoload.php';

ini_set('memory_limit', '256M');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$kernel = new Kernel();

$worker = Worker::create();
$factory = new Psr17Factory();
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

while ($request = $psr7->waitRequest()) {
    try {
        $response = $kernel->handle($request);
        $psr7->respond($response);
    } catch (\Throwable $e) {
        $psr7->getWorker()->error((string) $e);
    }
}
