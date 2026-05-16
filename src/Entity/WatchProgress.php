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

    #[ORM\Column(name: 'series_id', type: 'integer', nullable: true)]
    private ?int $seriesId = null;

    #[ORM\Column(name: 'series_title', type: 'string', length: 255, nullable: true)]
    private ?string $seriesTitle = null;

    #[ORM\Column(name: 'season', type: 'integer', nullable: true)]
    private ?int $season = null;

    #[ORM\Column(name: 'episode_num', type: 'integer', nullable: true)]
    private ?int $episodeNum = null;

    #[ORM\Column(name: 'episode_title', type: 'string', length: 255, nullable: true)]
    private ?string $episodeTitle = null;

    #[ORM\Column(name: 'cover', type: 'string', length: 1000, nullable: true)]
    private ?string $cover = null;

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

    public function getProfileId(): string { return $this->profileId; }
    public function getStreamId(): string { return $this->streamId; }
    public function getStreamType(): string { return $this->streamType; }
    public function getTimestampSeconds(): int { return $this->timestampSeconds; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getSeriesId(): ?int { return $this->seriesId; }
    public function getSeriesTitle(): ?string { return $this->seriesTitle; }
    public function getSeason(): ?int { return $this->season; }
    public function getEpisodeNum(): ?int { return $this->episodeNum; }
    public function getEpisodeTitle(): ?string { return $this->episodeTitle; }
    public function getCover(): ?string { return $this->cover; }
}
