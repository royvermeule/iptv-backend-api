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
        $stored = $this->redis->get('refresh_lookup:' . $refreshToken);
        if ($stored === null) {
            throw new \DomainException('Invalid or expired refresh token', 401);
        }

        [$userId, $profileId] = $this->parseStored($stored);

        $newRefreshToken = bin2hex(random_bytes(32));
        $this->redis->del('refresh_lookup:' . $refreshToken);

        $payload = ['sub' => $userId, 'iat' => time(), 'exp' => time() + 900];
        if ($profileId !== null) {
            $payload['profile_id'] = $profileId;
        }

        $accessToken = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        $this->redis->setex('refresh:' . $userId, 604800, $newRefreshToken);
        $this->redis->setex('refresh_lookup:' . $newRefreshToken, 604800, $stored);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }

    private function parseStored(string $stored): array
    {
        if (str_contains($stored, '|')) {
            [$userId, $profileId] = explode('|', $stored, 2);
            return [$userId, $profileId];
        }

        return [$stored, null];
    }
}
