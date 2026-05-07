<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\ForgotPasswordService;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class ForgotPasswordServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private ForgotPasswordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->service      = new ForgotPasswordService($this->em, $this->redis);
    }

    private function registerAndVerify(string $email = 'test@example.com'): \App\Entity\User
    {
        $user = $this->registration->register($email, 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $this->em->refresh($user);
        return $user;
    }

    public function test_handle_queues_reset_email_job(): void
    {
        $this->registerAndVerify();
        $this->redis->del('email_jobs');

        $this->service->handle('test@example.com');

        $job = $this->redis->rpop('email_jobs');
        $this->assertNotNull($job);
        $decoded = json_decode($job, true);
        $this->assertSame('reset_password', $decoded['type']);
        $this->assertSame('test@example.com', $decoded['email']);
    }

    public function test_handle_stores_reset_token_in_redis(): void
    {
        $user = $this->registerAndVerify();
        $this->redis->del('email_jobs');

        $this->service->handle('test@example.com');

        $job     = $this->redis->rpop('email_jobs');
        $decoded = json_decode($job, true);
        $stored  = $this->redis->get('password_reset:' . $decoded['token']);
        $this->assertSame($user->getId(), $stored);
    }

    public function test_handle_token_has_ttl(): void
    {
        $this->registerAndVerify();
        $this->redis->del('email_jobs');

        $this->service->handle('test@example.com');

        $job   = $this->redis->rpop('email_jobs');
        $token = json_decode($job, true)['token'];
        $ttl   = $this->redis->ttl('password_reset:' . $token);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    public function test_handle_does_nothing_for_unknown_email(): void
    {
        $this->redis->del('email_jobs');

        $this->service->handle('nobody@example.com');

        $this->assertNull($this->redis->rpop('email_jobs'));
    }
}
