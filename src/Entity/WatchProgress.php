<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'watch_progress')]
class WatchProgress
{
    #[ORM\Id]
    #[ORM\Column(name: 'profile_id', type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Id]
    #[ORM\Column(name: 'stream_id', type: 'string', length: 255)]
    private string $streamId;

    #[ORM\Column(name: 'stream_type', type: 'string', length: 20)]
    private string $streamType;

    #[ORM\Column(name: 'timestamp_seconds', type: 'integer')]
    private int $timestampSeconds;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $profileId,
        string $streamId,
        string $streamType,
        int $timestampSeconds,
    ) {
        $this->profileId        = $profileId;
        $this->streamId         = $streamId;
        $this->streamType       = $streamType;
        $this->timestampSeconds = $timestampSeconds;
        $this->updatedAt        = new \DateTimeImmutable();
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getStreamId(): string
    {
        return $this->streamId;
    }

    public function getStreamType(): string
    {
        return $this->streamType;
    }

    public function getTimestampSeconds(): int
    {
        return $this->timestampSeconds;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
