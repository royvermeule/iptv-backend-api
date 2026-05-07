<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\Profile;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManager;

class CredentialsService
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly EncryptionService $encryption
    ) {}

    public function store(
        string $userId,
        string $profileId,
        string $xtreamUrl,
        string $xtreamUsername,
        string $xtreamPassword
    ): void {
        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            'userId' => $userId,
            'id' => $profileId
        ]);

        if (!$profile) {
            throw new \DomainException('Profile could not be found', 404);
        }

        $profile->setCredentials(
            url: $this->encryption->encrypt($xtreamUrl),
            username: $this->encryption->encrypt($xtreamUsername),
            password: $this->encryption->encrypt($xtreamPassword),
        );

        $this->em->flush();
    }
}
