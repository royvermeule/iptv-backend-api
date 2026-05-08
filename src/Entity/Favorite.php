<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'favorites')]
class Favorite
{
    #[ORM\Id]
    #[ORM\Column(name: 'profile_id', type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Id]
    #[ORM\Column(name: 'stream_id', type: 'string', length: 255)]
    private string $streamId;

    #[ORM\Column(name: 'stream_type', type: 'string', length: 20)]
    private string $streamType;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $profileId, string $streamId, string $streamType)
    {
        $this->profileId  = $profileId;
        $this->streamId   = $streamId;
        $this->streamType = $streamType;
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getProfileId(): string          { return $this->profileId; }
    public function getStreamId(): string           { return $this->streamId; }
    public function getStreamType(): string         { return $this->streamType; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
