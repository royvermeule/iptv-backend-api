<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class VerifyEmailServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->service      = new VerifyEmailService($this->em);
    }

    public function test_verify_marks_user_as_verified(): void
    {
        $user = $this->registration->register('test@example.com', 'secret123');

        $this->service->verify($user->getVerificationToken());
        $this->em->refresh($user);

        $this->assertTrue($user->isVerified());
    }

    public function test_verify_clears_the_verification_token(): void
    {
        $user = $this->registration->register('test@example.com', 'secret123');

        $this->service->verify($user->getVerificationToken());
        $this->em->refresh($user);

        $this->assertNull($user->getVerificationToken());
    }

    public function test_verify_throws_404_for_invalid_token(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->service->verify('not-a-real-token');
    }

    public function test_verify_throws_404_when_token_already_used(): void
    {
        $user  = $this->registration->register('test@example.com', 'secret123');
        $token = $user->getVerificationToken();
        $this->service->verify($token);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->service->verify($token);
    }
}
