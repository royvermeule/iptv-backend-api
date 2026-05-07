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
        $userId = $this->redis->get('refresh_lookup:' . $refreshToken);
        if ($userId === null) {
            throw new \DomainException('Invalid or expired refresh token', 401);
        }

        $this->redis->del('refresh_lookup:' . $refreshToken);
        $this->redis->del('refresh:' . $userId);
    }
}
