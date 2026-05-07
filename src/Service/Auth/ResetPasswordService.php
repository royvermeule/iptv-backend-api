<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Predis\Client;

class ResetPasswordService
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function reset(string $token, string $newPassword): void
    {
        $userId = $this->redis->get('password_reset:' . $token);

        if ($userId === null) {
            throw new \DomainException('Invalid or expired reset token', 400);
        }

        $user = $this->em->find(User::class, $userId);

        if (!$user) {
            throw new \DomainException('Invalid or expired reset token', 400);
        }

        $user->setPasswordHash(sodium_crypto_pwhash_str(
            $newPassword,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        ));

        $this->em->flush();
        $this->redis->del('password_reset:' . $token);
    }
}
