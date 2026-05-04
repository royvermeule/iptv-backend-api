<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
]);

$mailer   = new App\Service\Mailer\MailerService();
$registry = new App\Jobs\JobRegistry();
$registry->register(new App\Jobs\EmailWorkerJob($redis, $mailer));

$app = new Symfony\Component\Console\Application('IPTV Job Runner', '1.0.0');
$app->addCommand(new App\Console\Command\JobsListCommand($registry));
$app->addCommand(new App\Console\Command\JobsRunCommand($registry));
$app->addCommand(new App\Console\Command\JobsRunAllCommand($registry));
$app->run();
