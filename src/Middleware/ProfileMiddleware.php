<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Profile;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ServerRequestInterface;

class ProfileMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Client $redis,
    ) {}

    public function process(ServerRequestInterface $request): ServerRequestInterface
    {
        $profileId = $request->getAttribute('profile_id');
        if (!$profileId) {
            throw new \DomainException('A profile must be selected', 403);
        }

        $userId  = $request->getAttribute('user_id');
        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            'userId' => $userId,
            'id'     => $profileId,
        ]);

        if (!$profile) {
            throw new \DomainException('Profile not found', 403);
        }

        if (!$profile->hasCredentials()) {
            throw new \DomainException('Profile has no IPTV credentials configured', 403);
        }

        $enc = new EncryptionService();

        return $request
            ->withAttribute('xtream_url',      $enc->decrypt($profile->getXtreamUrl()))
            ->withAttribute('xtream_username', $enc->decrypt($profile->getXtreamUsername()))
            ->withAttribute('xtream_password', $enc->decrypt($profile->getXtreamPassword()));
    }
}
