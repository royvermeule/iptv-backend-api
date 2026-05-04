<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Firebase\JWT\JWT;
use Predis\Client;

class RefreshService
{
    public function __construct(
        private readonly Client $redis
    ) {}

    public function refresh(string $refreshToken): array
    {
        $userId = $this->redis->get('refresh_lookup:' . $refreshToken);
        if ($userId === null) {
            throw new \DomainException('Invalid or expired refresh token', 401);
        }

        $oldToken = $refreshToken;

        $newRefreshToken = bin2hex(random_bytes(32));
        $this->redis->del('refresh_lookup:' . $oldToken);

        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $accessToken = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        $this->redis->setex('refresh:' . $userId, 604800, $newRefreshToken);
        $this->redis->setex('refresh_lookup:' . $newRefreshToken, 604800, $userId);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }
}
