<?php

declare(strict_types=1);

namespace Tests\Integration\Favorites;

use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\Favorites\FavoritesService;
use App\Service\Profile\ProfileService;
use Tests\IntegrationTestCase;

class FavoritesServiceTest extends IntegrationTestCase
{
    private FavoritesService $service;
    private string $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FavoritesService($this->em);

        $registration = new RegistrationService($this->em, $this->redis);
        $verification = new VerifyEmailService($this->em);
        $profiles     = new ProfileService($this->em);

        $user = $registration->register('test@example.com', 'secret123');
        $verification->verify($user->getVerificationToken());
        $this->em->refresh($user);

        $this->profileId = $profiles->create($user->getId(), 'Main', 'NL')->getId();
    }

    public function test_add_creates_favorite(): void
    {
        $favorite = $this->service->add($this->profileId, '1001', 'movie');

        $this->assertSame('1001', $favorite->getStreamId());
        $this->assertSame('movie', $favorite->getStreamType());
    }

    public function test_add_throws_409_when_already_favorited(): void
    {
        $this->service->add($this->profileId, '1001', 'movie');

        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(409);
        $this->service->add($this->profileId, '1001', 'movie');
    }

    public function test_add_throws_422_for_invalid_stream_type(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->add($this->profileId, '1001', 'series_episode');
    }

    public function test_add_accepts_all_valid_stream_types(): void
    {
        $this->service->add($this->profileId, '1', 'live');
        $this->service->add($this->profileId, '2', 'movie');
        $this->service->add($this->profileId, '3', 'series');

        $this->assertCount(3, $this->service->list($this->profileId));
    }

    public function test_remove_deletes_favorite(): void
    {
        $this->service->add($this->profileId, '1001', 'movie');
        $this->service->remove($this->profileId, '1001');

        $this->assertSame([], $this->service->list($this->profileId));
    }

    public function test_remove_throws_404_when_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(404);
        $this->service->remove($this->profileId, 'nonexistent');
    }

    public function test_list_returns_empty_for_new_profile(): void
    {
        $this->assertSame([], $this->service->list($this->profileId));
    }

    public function test_list_is_scoped_to_profile(): void
    {
        $reg   = new RegistrationService($this->em, $this->redis);
        $ver   = new VerifyEmailService($this->em);
        $profs = new ProfileService($this->em);

        $other        = $reg->register('other@example.com', 'secret123');
        $ver->verify($other->getVerificationToken());
        $this->em->refresh($other);
        $otherProfileId = $profs->create($other->getId(), 'Main', 'NL')->getId();

        $this->service->add($this->profileId, '1001', 'movie');

        $this->assertSame([], $this->service->list($otherProfileId));
    }

    public function test_add_allows_same_stream_for_different_profiles(): void
    {
        $reg   = new RegistrationService($this->em, $this->redis);
        $ver   = new VerifyEmailService($this->em);
        $profs = new ProfileService($this->em);

        $other        = $reg->register('other@example.com', 'secret123');
        $ver->verify($other->getVerificationToken());
        $this->em->refresh($other);
        $otherProfileId = $profs->create($other->getId(), 'Main', 'NL')->getId();

        $this->service->add($this->profileId, '1001', 'movie');
        $this->service->add($otherProfileId, '1001', 'movie');

        $this->assertCount(1, $this->service->list($this->profileId));
        $this->assertCount(1, $this->service->list($otherProfileId));
    }
}
