<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Firebase\JWT\JWT;
use Predis\Client;

class LoginService
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function login(string $email, string $password): array
    {
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$existing || !sodium_crypto_pwhash_str_verify($existing->getPasswordHash(), $password)) {
            throw new \DomainException('Invalid credentials', 401);
        }

        if (!$existing->isVerified()) {
            throw new \DomainException('Email not verified', 403);
        }

        $payload = [
            'sub' => $existing->getId(),
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $accessToken = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        $refreshToken = bin2hex(random_bytes(32));
        $this->redis->setex('refresh:' . $existing->getId(), 604800, $refreshToken);
        $this->redis->setex('refresh_lookup:' . $refreshToken, 604800, $existing->getId());

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }
}
