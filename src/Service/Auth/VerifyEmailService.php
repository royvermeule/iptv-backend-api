<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManager;

class VerifyEmailService
{
    public function __construct(
        private readonly EntityManager $em,
    ) {}

    public function verify(string $token): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['verificationToken' => $token]);
        if (!$user) {
            throw new \DomainException('Invalid verification token', 404);
        }

        $user->verify();
        $this->em->flush();
    }
}
