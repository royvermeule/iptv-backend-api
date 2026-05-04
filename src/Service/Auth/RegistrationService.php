<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Predis\Client;

class RegistrationService
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function register(string $email, string $password): User
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            throw new \DomainException('An account with this email already exists.', 409);
        }

        $passwordHash = sodium_crypto_pwhash_str(
            $password,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        );

        $verificationToken = bin2hex(random_bytes(32));

        $user = new User(
            email: $email,
            passwordHash: $passwordHash,
            verificationToken: $verificationToken,
        );

        $this->em->persist($user);
        $this->em->flush();

        $this->redis->lpush('email_jobs', [json_encode([
            'type'    => 'verify_email',
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
            'token'   => $verificationToken,
        ])]);

        return $user;
    }
}
