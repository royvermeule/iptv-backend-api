<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$em = App\Config\DoctrineFactory::create();
$tool = new Doctrine\ORM\Tools\SchemaTool($em);
$classes = $em->getMetadataFactory()->getAllMetadata();
$tool->createSchema($classes);

echo "Schema created successfully.\n";
