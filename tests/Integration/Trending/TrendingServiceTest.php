<?php

declare(strict_types=1);

namespace Tests\Integration\Trending;

use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerifyEmailService;
use App\Service\Profile\ProfileService;
use App\Service\Tmdb\TrendingService;
use Tests\IntegrationTestCase;

class TrendingServiceTest extends IntegrationTestCase
{
    private TrendingService $service;
    private string $profileId;
    private string $profileIdNoCountry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TrendingService($this->em, $this->redis);

        $registration = new RegistrationService($this->em, $this->redis);
        $verification = new VerifyEmailService($this->em);
        $profiles     = new ProfileService($this->em);

        $user = $registration->register('test@example.com', 'secret123');
        $verification->verify($user->getVerificationToken());
        $this->em->refresh($user);

        $this->profileId          = $profiles->create($user->getId(), 'Main', 'NL')->getId();
        $this->profileIdNoCountry = $profiles->create($user->getId(), 'Kids', null)->getId();
    }

    private function hasTmdbKey(): bool
    {
        return !empty($_ENV['TMDB_API_KEY']);
    }

    public function test_throws_422_when_profile_has_no_country_code(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->getTrending($this->profileIdNoCountry);
    }

    public function test_throws_422_for_nonexistent_profile(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(422);
        $this->service->getTrending('00000000-0000-0000-0000-000000000000');
    }

    public function test_returns_trending_data_with_correct_structure(): void
    {
        if (!$this->hasTmdbKey()) {
            $this->markTestSkipped('TMDB_API_KEY not set in .env.test.local');
        }

        $data = $this->service->getTrending($this->profileId);

        $this->assertArrayHasKey('region', $data);
        $this->assertArrayHasKey('movies', $data);
        $this->assertArrayHasKey('tv', $data);
        $this->assertSame('NL', $data['region']);
        $this->assertNotEmpty($data['movies']);
        $this->assertNotEmpty($data['tv']);
    }

    public function test_movie_items_have_required_fields(): void
    {
        if (!$this->hasTmdbKey()) {
            $this->markTestSkipped('TMDB_API_KEY not set in .env.test.local');
        }

        $data  = $this->service->getTrending($this->profileId);
        $movie = $data['movies'][0];

        $this->assertArrayHasKey('tmdb_id', $movie);
        $this->assertArrayHasKey('title', $movie);
        $this->assertArrayHasKey('year', $movie);
        $this->assertArrayHasKey('overview', $movie);
        $this->assertArrayHasKey('poster_path', $movie);
        $this->assertSame('movie', $movie['media_type']);
    }

    public function test_result_is_cached_in_redis(): void
    {
        if (!$this->hasTmdbKey()) {
            $this->markTestSkipped('TMDB_API_KEY not set in .env.test.local');
        }

        $this->service->getTrending($this->profileId);

        $cached = $this->redis->get('trending:NL');
        $this->assertNotNull($cached);
        $this->assertGreaterThan(0, $this->redis->ttl('trending:NL'));
    }

    public function test_second_call_uses_redis_cache(): void
    {
        if (!$this->hasTmdbKey()) {
            $this->markTestSkipped('TMDB_API_KEY not set in .env.test.local');
        }

        $first  = $this->service->getTrending($this->profileId);
        $second = $this->service->getTrending($this->profileId);

        $this->assertSame($first, $second);
    }
}
