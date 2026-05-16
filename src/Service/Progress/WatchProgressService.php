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

    public function upsert(string $profileId, string $streamId, string $streamType, int $timestampSeconds): void
    {
        if (!in_array($streamType, self::VALID_STREAM_TYPES, true)) {
            throw new \DomainException(
                'Invalid stream_type. Must be one of: ' . implode(', ', self::VALID_STREAM_TYPES),
                422
            );
        }

        $this->em->getConnection()->executeStatement(
            'INSERT INTO watch_progress (profile_id, stream_id, stream_type, timestamp_seconds, updated_at)
             VALUES (:profile_id, :stream_id, :stream_type, :timestamp_seconds, NOW())
             ON CONFLICT (profile_id, stream_id) DO UPDATE SET
                 stream_type       = EXCLUDED.stream_type,
                 timestamp_seconds = EXCLUDED.timestamp_seconds,
                 updated_at        = NOW()',
            [
                'profile_id'        => $profileId,
                'stream_id'         => $streamId,
                'stream_type'       => $streamType,
                'timestamp_seconds' => $timestampSeconds,
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
}
