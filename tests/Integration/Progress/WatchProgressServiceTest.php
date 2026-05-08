<?php

declare(strict_types=1);

namespace Tests\Integration\Progress;

use App\Entity\User;
use App\Entity\Profile;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\Profile\ProfileService;
use App\Service\Progress\WatchProgressService;
use Tests\IntegrationTestCase;

class WatchProgressServiceTest extends IntegrationTestCase
{
    private WatchProgressService $service;
    private string $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WatchProgressService($this->em);

        $registration = new RegistrationService($this->em, $this->redis);
        $verification = new VerifyEmailService($this->em);
        $profiles     = new ProfileService($this->em);

        $user = $registration->register('test@example.com', 'secret123');
        $verification->verify($user->getVerificationToken());
        $this->em->refresh($user);

        $profile         = $profiles->create($user->getId(), 'Main', 'NL');
        $this->profileId = $profile->getId();
    }

    public function test_upsert_creates_new_progress(): void
    {
        $this->service->upsert($this->profileId, '1001', 'movie', 120);
        $this->em->clear();

        $progress = $this->service->getOne($this->profileId, '1001');

        $this->assertNotNull($progress);
        $this->assertSame('1001', $progress->getStreamId());
        $this->assertSame('movie', $progress->getStreamType());
        $this->assertSame(120, $progress->getTimestampSeconds());
    }

    public function test_upsert_updates_existing_progress(): void
    {
        $this->service->upsert($this->profileId, '1001', 'movie', 120);
        $this->service->upsert($this->profileId, '1001', 'movie', 360);
        $this->em->clear();

        $progress = $this->service->getOne($this->profileId, '1001');

        $this->assertSame(360, $progress->getTimestampSeconds());
    }

    public function test_upsert_only_one_row_exists_after_two_upserts(): void
    {
        $this->service->upsert($this->profileId, '1001', 'movie', 120);
        $this->service->upsert($this->profileId, '1001', 'movie', 360);
        $this->em->clear();

        $all = $this->service->listAll($this->profileId);

        $this->assertCount(1, $all);
    }

    public function test_upsert_throws_422_for_invalid_stream_type(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->upsert($this->profileId, '1001', 'invalid_type', 120);
    }

    public function test_list_returns_empty_for_new_profile(): void
    {
        $this->assertSame([], $this->service->listAll($this->profileId));
    }

    public function test_list_returns_all_progress_ordered_by_updated_at(): void
    {
        $this->service->upsert($this->profileId, '1001', 'movie', 120);
        $this->service->upsert($this->profileId, '2002', 'series_episode', 45);
        $this->service->upsert($this->profileId, '1001', 'movie', 240);
        $this->em->clear();

        $all = $this->service->listAll($this->profileId);

        $this->assertCount(2, $all);
        $this->assertSame('1001', $all[0]->getStreamId());
    }

    public function test_list_is_scoped_to_profile(): void
    {
        $profiles  = new ProfileService($this->em);
        $reg       = new RegistrationService($this->em, $this->redis);
        $ver       = new VerifyEmailService($this->em);
        $other     = $reg->register('other@example.com', 'secret123');
        $ver->verify($other->getVerificationToken());
        $this->em->refresh($other);
        $otherProfile = $profiles->create($other->getId(), 'Main', 'NL');

        $this->service->upsert($this->profileId, '1001', 'movie', 120);
        $this->em->clear();

        $this->assertSame([], $this->service->listAll($otherProfile->getId()));
    }

    public function test_get_one_returns_null_when_not_found(): void
    {
        $this->assertNull($this->service->getOne($this->profileId, 'nonexistent'));
    }

    public function test_upsert_accepts_all_valid_stream_types(): void
    {
        $this->service->upsert($this->profileId, '1', 'live', 0);
        $this->service->upsert($this->profileId, '2', 'movie', 100);
        $this->service->upsert($this->profileId, '3', 'series_episode', 200);
        $this->em->clear();

        $this->assertCount(3, $this->service->listAll($this->profileId));
    }
}
