<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\LoginService;
use App\Service\Auth\RefreshService;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class RefreshServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private LoginService $login;
    private RefreshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->login        = new LoginService($this->em, $this->redis);
        $this->service      = new RefreshService($this->redis);
    }

    private function loginUser(): array
    {
        $user = $this->registration->register('test@example.com', 'secret123');
        $this->verification->verify($user->getVerificationToken());
        return $this->login->login('test@example.com', 'secret123');
    }

    public function test_refresh_returns_new_token_pair(): void
    {
        $tokens = $this->loginUser();

        $newTokens = $this->service->refresh($tokens['refresh_token']);

        $this->assertArrayHasKey('access_token', $newTokens);
        $this->assertArrayHasKey('refresh_token', $newTokens);
        $this->assertNotEmpty($newTokens['access_token']);
        $this->assertNotEmpty($newTokens['refresh_token']);
    }

    public function test_refresh_issues_a_different_refresh_token(): void
    {
        $tokens = $this->loginUser();

        $newTokens = $this->service->refresh($tokens['refresh_token']);

        $this->assertNotSame($tokens['refresh_token'], $newTokens['refresh_token']);
    }

    public function test_refresh_invalidates_the_old_token(): void
    {
        $tokens = $this->loginUser();
        $oldToken = $tokens['refresh_token'];
        $this->service->refresh($oldToken);

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->refresh($oldToken);
    }

    public function test_refresh_stores_new_token_in_redis(): void
    {
        $tokens    = $this->loginUser();
        $newTokens = $this->service->refresh($tokens['refresh_token']);

        $stored = $this->redis->get('refresh_lookup:' . $newTokens['refresh_token']);
        $this->assertNotNull($stored);
    }

    public function test_refresh_throws_401_for_invalid_token(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->service->refresh('not-a-real-token');
    }
}
