<?php

declare(strict_types=1);

namespace App\Service\Favorites;

use App\Entity\Favorite;
use Doctrine\ORM\EntityManager;

class FavoritesService
{
    private const VALID_STREAM_TYPES = ['live', 'movie', 'series'];

    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function add(string $profileId, string $streamId, string $streamType): Favorite
    {
        if (!in_array($streamType, self::VALID_STREAM_TYPES, true)) {
            throw new \DomainException(
                'Invalid stream_type. Must be one of: ' . implode(', ', self::VALID_STREAM_TYPES),
                422
            );
        }

        $existing = $this->em->getRepository(Favorite::class)->findOneBy([
            'profileId' => $profileId,
            'streamId'  => $streamId,
        ]);

        if ($existing) {
            throw new \DomainException('Stream is already in favorites', 409);
        }

        $favorite = new Favorite($profileId, $streamId, $streamType);
        $this->em->persist($favorite);
        $this->em->flush();

        return $favorite;
    }

    public function remove(string $profileId, string $streamId): void
    {
        $favorite = $this->em->getRepository(Favorite::class)->findOneBy([
            'profileId' => $profileId,
            'streamId'  => $streamId,
        ]);

        if (!$favorite) {
            throw new \DomainException('Favorite not found', 404);
        }

        $this->em->remove($favorite);
        $this->em->flush();
    }

    /** @return array<Favorite> */
    public function list(string $profileId): array
    {
        return $this->em->getRepository(Favorite::class)->findBy(
            ['profileId' => $profileId],
            ['createdAt' => 'DESC']
        );
    }
}
