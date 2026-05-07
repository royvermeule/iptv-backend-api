<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\LoginService;
use App\Service\Auth\LogoutService;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class LogoutServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private LoginService $login;
    private LogoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->login        = new LoginService($this->em, $this->redis);
        $this->service      = new LogoutService($this->redis);
    }

    private function loginUser(): array
    {
        $user = $this->registration->register('test@example.com', 'secret123');
        $this->verification->verify($user->getVerificationToken());
        return $this->login->login('test@example.com', 'secret123');
    }

    public function test_logout_removes_refresh_lookup_key(): void
    {
        $tokens = $this->loginUser();

        $this->service->logout($tokens['refresh_token']);

        $this->assertNull($this->redis->get('refresh_lookup:' . $tokens['refresh_token']));
    }

    public function test_logout_removes_refresh_key(): void
    {
        $user = $this->registration->register('test2@example.com', 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $tokens = $this->login->login('test2@example.com', 'secret123');

        $this->service->logout($tokens['refresh_token']);

        $this->assertNull($this->redis->get('refresh:' . $user->getId()));
    }

    public function test_logout_throws_401_for_invalid_token(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->logout('not-a-real-token');
    }

    public function test_logout_throws_401_when_already_logged_out(): void
    {
        $tokens = $this->loginUser();
        $this->service->logout($tokens['refresh_token']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->logout($tokens['refresh_token']);
    }
}
