<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Tools\Console\Command;
use Symfony\Component\Console\Application;

$em = App\Config\DoctrineFactory::create();

$config = new Configuration();
$config->addMigrationsDirectory('App\\Migrations', __DIR__ . '/../migrations');
$config->setMetadataStorageConfiguration(new TableMetadataStorageConfiguration());

$dependencyFactory = DependencyFactory::fromEntityManager(
    new ExistingConfiguration($config),
    new ExistingEntityManager($em),
);

$app = new Application('Doctrine Migrations');
$app->addCommands([
    new Command\DiffCommand($dependencyFactory),
    new Command\MigrateCommand($dependencyFactory),
    new Command\StatusCommand($dependencyFactory),
    new Command\GenerateCommand($dependencyFactory),
    new Command\ExecuteCommand($dependencyFactory),
    new Command\ListCommand($dependencyFactory),
    new Command\LatestCommand($dependencyFactory),
    new Command\RollupCommand($dependencyFactory),
    new Command\SyncMetadataCommand($dependencyFactory),
    new Command\VersionCommand($dependencyFactory),
]);
$app->run();
