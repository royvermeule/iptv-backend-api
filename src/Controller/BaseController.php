<?php

declare(strict_types=1);

namespace App\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController
{
    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    protected function getUserId(ServerRequestInterface $request): string
    {
        return (string) $request->getAttribute('user_id');
    }

    protected function getProfileId(ServerRequestInterface $request): string
    {
        $profileId = $request->getAttribute('profile_id');
        if ($profileId === null) {
            throw new \DomainException('No profile selected', 403);
        }
        return (string) $profileId;
    }

    protected function json(array $data, int $status = 200): ResponseInterface
    {
        $body = $this->factory->createStream(json_encode($data, JSON_THROW_ON_ERROR));

        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
