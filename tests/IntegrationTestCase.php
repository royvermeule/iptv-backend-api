<?php

declare(strict_types=1);

namespace Tests;

use App\Config\DoctrineFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Predis\Client;

abstract class IntegrationTestCase extends TestCase
{
    protected EntityManager $em;
    protected Client $redis;

    protected function setUp(): void
    {
        $this->em = DoctrineFactory::create();
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);

        $this->resetSchema();
        $this->redis->flushdb();
    }

    protected function tearDown(): void
    {
        $this->em->close();
    }

    // Drop and recreate all tables so each test starts with a clean slate.
    private function resetSchema(): void
    {
        $tool    = new SchemaTool($this->em);
        $classes = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }
}
