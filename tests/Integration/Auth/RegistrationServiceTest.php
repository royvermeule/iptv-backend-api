<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\RegistrationService;
use Tests\IntegrationTestCase;

class RegistrationServiceTest extends IntegrationTestCase
{
    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegistrationService($this->em, $this->redis);
    }

    public function test_register_creates_unverified_user(): void
    {
        $user = $this->service->register('test@example.com', 'secret123');

        $this->assertNotEmpty($user->getId());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertFalse($user->isVerified());
        $this->assertNotNull($user->getVerificationToken());
    }

    public function test_register_hashes_password(): void
    {
        $user = $this->service->register('test@example.com', 'secret123');

        $this->assertNotSame('secret123', $user->getPasswordHash());
        $this->assertTrue(sodium_crypto_pwhash_str_verify($user->getPasswordHash(), 'secret123'));
    }

    public function test_register_throws_on_duplicate_email(): void
    {
        $this->service->register('test@example.com', 'secret123');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);
        $this->service->register('test@example.com', 'anotherpassword');
    }

    public function test_register_queues_email_job(): void
    {
        $user = $this->service->register('test@example.com', 'secret123');

        $job = $this->redis->rpop('email_jobs');
        $this->assertNotNull($job);

        $decoded = json_decode($job, true);
        $this->assertSame('verify_email', $decoded['type']);
        $this->assertSame('test@example.com', $decoded['email']);
        $this->assertSame($user->getId(), $decoded['user_id']);
    }
}
