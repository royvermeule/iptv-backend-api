<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Entity\User;
use App\Service\Auth\LoginService;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class LoginServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private LoginService $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->login        = new LoginService($this->em, $this->redis);
    }

    private function registerAndVerify(string $email, string $password): User
    {
        $user = $this->registration->register($email, $password);
        $this->verification->verify($user->getVerificationToken());
        $this->em->refresh($user);
        return $user;
    }

    public function test_login_returns_tokens_for_verified_user(): void
    {
        $this->registerAndVerify('test@example.com', 'secret123');

        $tokens = $this->login->login('test@example.com', 'secret123');

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertNotEmpty($tokens['access_token']);
        $this->assertNotEmpty($tokens['refresh_token']);
    }

    public function test_login_stores_refresh_token_in_redis(): void
    {
        $user = $this->registerAndVerify('test@example.com', 'secret123');
        $tokens = $this->login->login('test@example.com', 'secret123');

        $stored = $this->redis->get('refresh:' . $user->getId());
        $this->assertSame($tokens['refresh_token'], $stored);
    }

    public function test_login_throws_401_for_wrong_password(): void
    {
        $this->registerAndVerify('test@example.com', 'secret123');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->login->login('test@example.com', 'wrongpassword');
    }

    public function test_login_throws_401_for_unknown_email(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        $this->login->login('nobody@example.com', 'secret123');
    }

    public function test_login_throws_403_for_unverified_user(): void
    {
        $this->registration->register('test@example.com', 'secret123');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(403);
        $this->login->login('test@example.com', 'secret123');
    }
}
