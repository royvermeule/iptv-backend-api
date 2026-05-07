<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Service\Auth\ForgotPasswordService;
use App\Service\Auth\LoginService;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\ResetPasswordService;
use App\Service\Auth\VerifyEmailService;
use Tests\IntegrationTestCase;

class ResetPasswordServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private ForgotPasswordService $forgot;
    private ResetPasswordService $service;
    private LoginService $login;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->forgot       = new ForgotPasswordService($this->em, $this->redis);
        $this->service      = new ResetPasswordService($this->em, $this->redis);
        $this->login        = new LoginService($this->em, $this->redis);
    }

    private function getResetToken(string $email = 'test@example.com'): string
    {
        $user = $this->registration->register($email, 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $this->redis->del('email_jobs');
        $this->forgot->handle($email);
        $job = $this->redis->rpop('email_jobs');
        return json_decode($job, true)['token'];
    }

    public function test_reset_updates_password_hash(): void
    {
        $token = $this->getResetToken();

        $this->service->reset($token, 'newpassword123');

        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertTrue(sodium_crypto_pwhash_str_verify($user->getPasswordHash(), 'newpassword123'));
    }

    public function test_reset_old_password_no_longer_works(): void
    {
        $token = $this->getResetToken();
        $this->service->reset($token, 'newpassword123');

        $this->expectException(\DomainException::class);
        $this->login->login('test@example.com', 'secret123');
    }

    public function test_reset_new_password_works_for_login(): void
    {
        $token  = $this->getResetToken();
        $this->service->reset($token, 'newpassword123');

        $tokens = $this->login->login('test@example.com', 'newpassword123');

        $this->assertArrayHasKey('access_token', $tokens);
    }

    public function test_reset_deletes_token_from_redis(): void
    {
        $token = $this->getResetToken();

        $this->service->reset($token, 'newpassword123');

        $this->assertNull($this->redis->get('password_reset:' . $token));
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $token = $this->getResetToken();
        $this->service->reset($token, 'newpassword123');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(400);
        $this->service->reset($token, 'anotherpassword');
    }

    public function test_reset_throws_400_for_invalid_token(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(400);
        $this->service->reset('not-a-real-token', 'newpassword123');
    }
}
