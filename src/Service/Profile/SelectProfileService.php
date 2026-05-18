<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\Profile;
use App\Service\EncryptionService;
use App\Service\Xtream\XtreamService;
use Doctrine\ORM\EntityManager;
use Firebase\JWT\JWT;
use Predis\Client;

class SelectProfileService
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function select(
        string $userId,
        string $profileId,
        ?int $pin = null,
    ): array {
        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            "userId" => $userId,
            "id" => $profileId,
        ]);

        if (!$profile) {
            throw new \DomainException("Profile not found", 404);
        }

        if ($profile->getPin() !== null && $profile->getPin() !== $pin) {
            throw new \DomainException("Invalid PIN", 401);
        }

        if (!$profile->hasCredentials()) {
            throw new \DomainException(
                "Profile has no IPTV credentials configured",
                422,
            );
        }

        $enc = new EncryptionService();
        $url = $enc->decrypt($profile->getXtreamUrl());
        $username = $enc->decrypt($profile->getXtreamUsername());
        $password = $enc->decrypt($profile->getXtreamPassword());

        if (!new XtreamService()->testCredentials($url, $username, $password)) {
            throw new \DomainException(
                "IPTV credentials are invalid or the server is unreachable",
                422,
            );
        }

        $accessToken = JWT::encode(
            [
                "sub" => $userId,
                "profile_id" => $profileId,
                "iat" => time(),
                "exp" => time() + 900,
            ],
            $_ENV["JWT_SECRET"],
            "HS256",
        );

        $refreshToken = bin2hex(random_bytes(32));
        $this->redis->setex("refresh:" . $userId, 604800, $refreshToken);
        $this->redis->setex(
            "refresh_lookup:" . $refreshToken,
            604800,
            $userId . "|" . $profileId,
        );

        return [
            "access_token" => $accessToken,
            "refresh_token" => $refreshToken,
        ];
    }
}
