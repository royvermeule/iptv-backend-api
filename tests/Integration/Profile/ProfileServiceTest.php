<?php

declare(strict_types=1);

namespace Tests\Integration\Profile;

use App\Entity\User;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\Profile\ProfileService;
use Tests\IntegrationTestCase;

class ProfileServiceTest extends IntegrationTestCase
{
    private RegistrationService $registration;
    private VerifyEmailService $verification;
    private ProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registration = new RegistrationService($this->em, $this->redis);
        $this->verification = new VerifyEmailService($this->em);
        $this->profiles     = new ProfileService($this->em);
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = $this->registration->register($email, 'secret123');
        $this->verification->verify($user->getVerificationToken());
        $this->em->refresh($user);
        return $user;
    }

    public function test_list_returns_empty_array_for_new_user(): void
    {
        $user = $this->createUser();

        $result = $this->profiles->list($user->getId());

        $this->assertSame([], $result);
    }

    public function test_create_persists_profile(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->assertSame($user->getId(), $profile->getUserId());
        $this->assertSame('Main', $profile->getName());
        $this->assertSame('NL', $profile->getCountryCode());
        $this->assertFalse($profile->hasCredentials());
    }

    public function test_list_returns_created_profiles(): void
    {
        $user = $this->createUser();
        $this->profiles->create($user->getId(), 'Main', 'NL');
        $this->profiles->create($user->getId(), 'Kids', 'NL');

        $result = $this->profiles->list($user->getId());

        $this->assertCount(2, $result);
    }

    public function test_list_only_returns_profiles_for_the_given_user(): void
    {
        $userA = $this->createUser('a@example.com');
        $userB = $this->createUser('b@example.com');
        $this->profiles->create($userA->getId(), 'Main', 'NL');

        $result = $this->profiles->list($userB->getId());

        $this->assertSame([], $result);
    }

    public function test_create_throws_409_for_duplicate_name(): void
    {
        $user = $this->createUser();
        $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);
        $this->profiles->create($user->getId(), 'Main', 'NL');
    }

    public function test_create_duplicate_name_check_is_case_insensitive(): void
    {
        $user = $this->createUser();
        $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);
        $this->profiles->create($user->getId(), 'MAIN', 'NL');
    }

    public function test_create_allows_same_name_for_different_users(): void
    {
        $userA = $this->createUser('a@example.com');
        $userB = $this->createUser('b@example.com');
        $this->profiles->create($userA->getId(), 'Main', 'NL');

        $profile = $this->profiles->create($userB->getId(), 'Main', 'NL');

        $this->assertSame('Main', $profile->getName());
    }

    public function test_delete_removes_profile(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->profiles->delete($user->getId(), $profile->getId());

        $this->assertSame([], $this->profiles->list($user->getId()));
    }

    public function test_delete_throws_404_for_nonexistent_profile(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->profiles->delete($user->getId(), '00000000-0000-0000-0000-000000000000');
    }

    public function test_delete_throws_404_for_profile_belonging_to_another_user(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $profile = $this->profiles->create($userA->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->profiles->delete($userB->getId(), $profile->getId());
    }

    public function test_update_changes_name(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $updated = $this->profiles->update($user->getId(), $profile->getId(), 'Primary', null);

        $this->assertSame('Primary', $updated->getName());
        $this->assertSame('NL', $updated->getCountryCode());
    }

    public function test_update_changes_country_code(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $updated = $this->profiles->update($user->getId(), $profile->getId(), null, 'DE');

        $this->assertSame('Main', $updated->getName());
        $this->assertSame('DE', $updated->getCountryCode());
    }

    public function test_update_clears_country_code_when_empty_string(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $updated = $this->profiles->update($user->getId(), $profile->getId(), null, '');

        $this->assertNull($updated->getCountryCode());
    }

    public function test_update_throws_409_when_name_conflicts_with_another_profile(): void
    {
        $user = $this->createUser();
        $this->profiles->create($user->getId(), 'Kids', 'NL');
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);
        $this->profiles->update($user->getId(), $profile->getId(), 'Kids', null);
    }

    public function test_update_allows_keeping_same_name(): void
    {
        $user    = $this->createUser();
        $profile = $this->profiles->create($user->getId(), 'Main', 'NL');

        $updated = $this->profiles->update($user->getId(), $profile->getId(), 'Main', 'DE');

        $this->assertSame('Main', $updated->getName());
        $this->assertSame('DE', $updated->getCountryCode());
    }

    public function test_update_throws_404_for_nonexistent_profile(): void
    {
        $user = $this->createUser();

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->profiles->update($user->getId(), '00000000-0000-0000-0000-000000000000', 'New', null);
    }

    public function test_update_throws_404_for_profile_belonging_to_another_user(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $profile = $this->profiles->create($userA->getId(), 'Main', 'NL');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->profiles->update($userB->getId(), $profile->getId(), 'New', null);
    }
}
