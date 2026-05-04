<?php

declare(strict_types=1);

namespace App\Config;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class DoctrineFactory
{
    public static function create(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Entity'],
            isDevMode: ($_ENV['APP_ENV'] ?? 'prod') === 'dev',
        );

        $connection = DriverManager::getConnection([
            'driver'   => 'pdo_pgsql',
            'host'     => $_ENV['DB_HOST'],
            'port'     => (int) ($_ENV['DB_PORT'] ?? 5432),
            'dbname'   => $_ENV['DB_NAME'],
            'user'     => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ]);

        return new EntityManager($connection, $config);
    }
}
