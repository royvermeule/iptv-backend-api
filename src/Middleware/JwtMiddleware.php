<?php

declare(strict_types=1);

namespace App\Middleware;

use Doctrine\ORM\EntityManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Predis\Client;
use Psr\Http\Message\ServerRequestInterface;

class JwtMiddleware implements MiddlewareInterface
{
    public function __construct(EntityManager $em, Client $redis) {}

    public function process(ServerRequestInterface $request): ServerRequestInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            throw new \DomainException('Missing or invalid Authorization header', 401);
        }

        $token = substr($header, 7);

        try {
            $payload = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
        } catch (\Throwable) {
            throw new \DomainException('Invalid or expired token', 401);
        }

        if (empty($payload->sub)) {
            throw new \DomainException('Invalid token payload', 401);
        }

        $request = $request->withAttribute('user_id', $payload->sub);

        if (!empty($payload->profile_id)) {
            $request = $request->withAttribute('profile_id', $payload->profile_id);
        }

        return $request;
    }
}
