<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Predis\Client;

class LogoutService
{
    public function __construct(
        private readonly Client $redis
    ) {}

    public function logout(string $refreshToken): void
    {
        $stored = $this->redis->get('refresh_lookup:' . $refreshToken);
        if ($stored === null) {
            throw new \DomainException('Invalid or expired refresh token', 401);
        }

        $userId = str_contains($stored, '|') ? explode('|', $stored, 2)[0] : $stored;

        $this->redis->del('refresh_lookup:' . $refreshToken);
        $this->redis->del('refresh:' . $userId);
    }
}
