<?php

declare(strict_types=1);

namespace Tests\Integration\Profile;

use App\Entity\Profile;
use App\Entity\User;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\EncryptionService;
use App\Service\Profile\CredentialsService;
use App\Service\Profile\ProfileService;
use Tests\IntegrationTestCase;

class CredentialsServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private ProfileService $profiles;
    private CredentialsService $credentials;
    private EncryptionService $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->encryption   = new EncryptionService();
        $this->profiles     = new ProfileService($this->em);
        $this->credentials  = new CredentialsService($this->em, $this->encryption);
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = $this->registration->register($email, 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $this->em->refresh($user);
        return $user;
    }

    private function createProfile(string $userId, string $name = 'Main'): Profile
    {
        return $this->profiles->create($userId, $name, 'NL');
    }

    public function test_store_saves_credentials_to_profile(): void
    {
        $user    = $this->createUser();
        $profile = $this->createProfile($user->getId());

        $this->credentials->store($user->getId(), $profile->getId(), 'http://provider.com', 'user1', 'pass1');

        $this->em->refresh($profile);
        $this->assertTrue($profile->hasCredentials());
    }

    public function test_store_encrypts_credentials_at_rest(): void
    {
        $user    = $this->createUser();
        $profile = $this->createProfile($user->getId());

        $this->credentials->store($user->getId(), $profile->getId(), 'http://provider.com', 'user1', 'pass1');

        $this->em->refresh($profile);
        $this->assertNotSame('http://provider.com', $profile->getXtreamUrl());
        $this->assertNotSame('user1', $profile->getXtreamUsername());
        $this->assertNotSame('pass1', $profile->getXtreamPassword());
    }

    public function test_store_credentials_are_decryptable(): void
    {
        $user    = $this->createUser();
        $profile = $this->createProfile($user->getId());

        $this->credentials->store($user->getId(), $profile->getId(), 'http://provider.com', 'user1', 'pass1');

        $this->em->refresh($profile);
        $this->assertSame('http://provider.com', $this->encryption->decrypt($profile->getXtreamUrl()));
        $this->assertSame('user1', $this->encryption->decrypt($profile->getXtreamUsername()));
        $this->assertSame('pass1', $this->encryption->decrypt($profile->getXtreamPassword()));
    }

    public function test_store_overwrites_existing_credentials(): void
    {
        $user    = $this->createUser();
        $profile = $this->createProfile($user->getId());
        $this->credentials->store($user->getId(), $profile->getId(), 'http://old.com', 'olduser', 'oldpass');

        $this->credentials->store($user->getId(), $profile->getId(), 'http://new.com', 'newuser', 'newpass');

        $this->em->refresh($profile);
        $this->assertSame('http://new.com', $this->encryption->decrypt($profile->getXtreamUrl()));
    }

    public function test_store_throws_404_for_nonexistent_profile(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->credentials->store($user->getId(), '00000000-0000-0000-0000-000000000000', 'http://provider.com', 'user1', 'pass1');
    }

    public function test_store_throws_404_for_profile_belonging_to_another_user(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $profile = $this->createProfile($userA->getId());

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->credentials->store($userB->getId(), $profile->getId(), 'http://provider.com', 'user1', 'pass1');
    }
}
