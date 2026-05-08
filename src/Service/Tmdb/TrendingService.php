<?php

declare(strict_types=1);

namespace App\Service\Tmdb;

use App\Entity\Profile;
use Doctrine\ORM\EntityManager;
use Predis\Client;

class TrendingService
{
    private const TTL = 43200; // 12 hours

    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function getTrending(string $profileId): array
    {
        $profile = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$profile || !$profile->getCountryCode()) {
            throw new \DomainException('Profile has no country code configured', 422);
        }

        $region   = strtoupper($profile->getCountryCode());
        $cacheKey = 'trending:' . $region;

        $cached = $this->redis->get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $data = (new TmdbService())->fetchTrending($region);

        $this->redis->setex($cacheKey, self::TTL, json_encode($data, JSON_PRESERVE_ZERO_FRACTION));

        return $data;
    }
}
