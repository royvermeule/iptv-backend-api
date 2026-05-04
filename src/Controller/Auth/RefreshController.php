<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\BaseController;
use App\Service\Auth\RefreshService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RefreshController extends BaseController
{
    private readonly RefreshService $service;

    public function __construct(EntityManager $_em, Client $redis)
    {
        parent::__construct();

        $this->service = new RefreshService($redis);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $refreshToken = (string) ($data['refresh_token'] ?? '');

        if ($refreshToken === '') {
            return $this->json(['error' => 'refresh_token is required.'], 400);
        }

        try {
            $tokens = $this->service->refresh($refreshToken);
            return $this->json($tokens);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
