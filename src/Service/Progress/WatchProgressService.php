<?php

declare(strict_types=1);

namespace App\Service\Progress;

use App\Entity\WatchProgress;
use Doctrine\ORM\EntityManager;

class WatchProgressService
{
    private const VALID_STREAM_TYPES = ['live', 'movie', 'series_episode'];

    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function upsert(
        string $profileId,
        string $streamId,
        string $streamType,
        int $timestampSeconds,
        ?int $seriesId = null,
        ?string $seriesTitle = null,
        ?int $season = null,
        ?int $episodeNum = null,
        ?string $episodeTitle = null,
        ?string $cover = null,
    ): void {
        if (!in_array($streamType, self::VALID_STREAM_TYPES, true)) {
            throw new \DomainException(
                'Invalid stream_type. Must be one of: ' . implode(', ', self::VALID_STREAM_TYPES),
                422
            );
        }

        $this->em->getConnection()->executeStatement(
            'INSERT INTO watch_progress
                 (profile_id, stream_id, stream_type, timestamp_seconds, updated_at,
                  series_id, series_title, season, episode_num, episode_title, cover)
             VALUES
                 (:profile_id, :stream_id, :stream_type, :timestamp_seconds, NOW(),
                  :series_id, :series_title, :season, :episode_num, :episode_title, :cover)
             ON CONFLICT (profile_id, stream_id) DO UPDATE SET
                 stream_type       = EXCLUDED.stream_type,
                 timestamp_seconds = EXCLUDED.timestamp_seconds,
                 updated_at        = NOW(),
                 series_id         = COALESCE(EXCLUDED.series_id,    watch_progress.series_id),
                 series_title      = COALESCE(EXCLUDED.series_title,  watch_progress.series_title),
                 season            = COALESCE(EXCLUDED.season,        watch_progress.season),
                 episode_num       = COALESCE(EXCLUDED.episode_num,   watch_progress.episode_num),
                 episode_title     = COALESCE(EXCLUDED.episode_title, watch_progress.episode_title),
                 cover             = COALESCE(EXCLUDED.cover,         watch_progress.cover)',
            [
                'profile_id'        => $profileId,
                'stream_id'         => $streamId,
                'stream_type'       => $streamType,
                'timestamp_seconds' => $timestampSeconds,
                'series_id'         => $seriesId,
                'series_title'      => $seriesTitle,
                'season'            => $season,
                'episode_num'       => $episodeNum,
                'episode_title'     => $episodeTitle,
                'cover'             => $cover,
            ]
        );
    }

    /** @return array<WatchProgress> */
    public function listAll(string $profileId): array
    {
        return $this->em->getRepository(WatchProgress::class)->findBy(
            ['profileId' => $profileId],
            ['updatedAt' => 'DESC']
        );
    }

    public function getOne(string $profileId, string $streamId): ?WatchProgress
    {
        return $this->em->getRepository(WatchProgress::class)->findOneBy([
            'profileId' => $profileId,
            'streamId'  => $streamId,
        ]);
    }

    public function delete(string $profileId, string $streamId): void
    {
        $progress = $this->getOne($profileId, $streamId);

        if (!$progress) {
            throw new \DomainException('Progress not found', 404);
        }

        $this->em->remove($progress);
        $this->em->flush();
    }

    public function deleteBySeries(string $profileId, int $seriesId): void
    {
        $this->em->getConnection()->executeStatement(
            'DELETE FROM watch_progress WHERE profile_id = :profile_id AND series_id = :series_id',
            ['profile_id' => $profileId, 'series_id' => $seriesId]
        );
    }
}
