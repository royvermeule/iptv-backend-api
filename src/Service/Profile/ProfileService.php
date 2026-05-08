<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\Profile;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

class ProfileService
{
    private readonly EntityRepository $profileRepo;

    public function __construct(
        private readonly EntityManager $em,
    ) {
        $this->profileRepo = $this->em->getRepository(Profile::class);
    }

    /**
     * @return array<Profile>
     */
    public function list(string $userId): array
    {
        $profiles = $this->profileRepo->findBy(['userId' => $userId]);
        return $profiles;
    }

    public function create(string $userId, string $name, ?string $countryCode = null): Profile
    {
        $existing = $this->list($userId);
        foreach ($existing as $profile) {
            if (strtolower($name) === strtolower($profile->getName())) {
                throw new \DomainException(
                    'A profile with this name already exists, for the current user',
                    409
                );
            }
        }
        $profile = new Profile($userId, $name, $countryCode);
        $this->em->persist($profile);
        $this->em->flush();

        return $profile;
    }

    public function update(string $userId, string $profileId, ?string $name, ?string $countryCode): Profile
    {
        $profile = $this->profileRepo->findOneBy([
            'userId' => $userId,
            'id'     => $profileId,
        ]);

        if (!$profile) {
            throw new \DomainException('Profile could not be found', 404);
        }

        if ($name !== null) {
            $existing = $this->list($userId);
            foreach ($existing as $other) {
                if ($other->getId() !== $profileId && strtolower($name) === strtolower($other->getName())) {
                    throw new \DomainException('A profile with this name already exists, for the current user', 409);
                }
            }
            $profile->setName($name);
        }

        if ($countryCode !== null) {
            $profile->setCountryCode($countryCode === '' ? null : $countryCode);
        }

        $this->em->flush();

        return $profile;
    }

    public function delete(string $userId, string $profileId): void
    {
        $profile = $this->profileRepo->findOneBy([
            'userId' => $userId,
            'id' => $profileId
        ]);

        if (!$profile) {
            throw new \DomainException('Profile could not be found', 404);
        }

        $this->em->remove($profile);
        $this->em->flush();
    }
}
