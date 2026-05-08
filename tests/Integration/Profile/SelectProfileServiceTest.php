<?php

declare(strict_types=1);

namespace Tests\Integration\Profile;

use App\Entity\User;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\EncryptionService;
use App\Service\Profile\CredentialsService;
use App\Service\Profile\ProfileService;
use App\Service\Profile\SelectProfileService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\IntegrationTestCase;

class SelectProfileServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private ProfileService $profiles;
    private CredentialsService $credentials;
    private SelectProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->profiles     = new ProfileService($this->em);
        $this->credentials  = new CredentialsService($this->em, new EncryptionService());
        $this->service      = new SelectProfileService($this->em, $this->redis);
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = $this->registration->register($email, 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $this->em->refresh($user);
        return $user;
    }

    private function hasRealCredentials(): bool
    {
        return !empty($_ENV['XTREAM_TEST_URL'])
            && !empty($_ENV['XTREAM_TEST_USERNAME'])
            && !empty($_ENV['XTREAM_TEST_PASSWORD']);
    }

    public function test_select_throws_404_for_nonexistent_profile(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->service->select($user->getId(), '00000000-0000-0000-0000-000000000000');
    }

    public function test_select_throws_404_for_profile_belonging_to_another_user(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $profile = $this->profiles->create($userA->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->service->select($userB->getId(), $profile->getId());
    }

    public function test_select_throws_422_when_profile_has_no_credentials(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->select($user->getId(), $profile->getId());
    }

    public function test_select_throws_422_when_server_is_unreachable(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');
        $this->credentials->store($user->getId(), $profile->getId(), 'http://127.0.0.1:1', 'user', 'pass');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->select($user->getId(), $profile->getId());
    }

    public function test_select_returns_token_pair_with_profile_id_embedded(): void
    {
        if (!$this->hasRealCredentials()) {
            $this->markTestSkipped('XTREAM_TEST_URL/USERNAME/PASSWORD not set in .env.test.local');
        }

        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');
        $this->credentials->store(
            $user->getId(),
            $profile->getId(),
            $_ENV['XTREAM_TEST_URL'],
            $_ENV['XTREAM_TEST_USERNAME'],
            $_ENV['XTREAM_TEST_PASSWORD'],
        );

        $tokens = $this->service->select($user->getId(), $profile->getId());

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);

        $payload = JWT::decode($tokens['access_token'], new Key($_ENV['JWT_SECRET'], 'HS256'));
        $this->assertSame($user->getId(), $payload->sub);
        $this->assertSame($profile->getId(), $payload->profile_id);
    }

    public function test_select_stores_profile_scoped_refresh_token_in_redis(): void
    {
        if (!$this->hasRealCredentials()) {
            $this->markTestSkipped('XTREAM_TEST_URL/USERNAME/PASSWORD not set in .env.test.local');
        }

        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');
        $this->credentials->store(
            $user->getId(),
            $profile->getId(),
            $_ENV['XTREAM_TEST_URL'],
            $_ENV['XTREAM_TEST_USERNAME'],
            $_ENV['XTREAM_TEST_PASSWORD'],
        );

        $tokens = $this->service->select($user->getId(), $profile->getId());

        $stored = $this->redis->get('refresh_lookup:' . $tokens['refresh_token']);
        $this->assertSame($user->getId() . '|' . $profile->getId(), $stored);
    }
}
