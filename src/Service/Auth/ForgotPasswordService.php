<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Predis\Client;

class ForgotPasswordService
{
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function handle(string $email): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        // Return silently if email not found — don't leak whether an account exists.
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->redis->setex('password_reset:' . $token, self::TTL_SECONDS, $user->getId());

        $this->redis->lpush('email_jobs', [json_encode([
            'type'            => 'reset_password',
            'email'           => $email,
            'token'           => $token,
            'expiry_minutes'  => self::TTL_SECONDS / 60,
        ])]);
    }
}
