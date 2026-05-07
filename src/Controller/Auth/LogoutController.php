<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\BaseController;
use App\Service\Auth\LogoutService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LogoutController extends BaseController
{
    private readonly LogoutService $service;

    public function __construct(EntityManager $_em, Client $redis)
    {
        parent::__construct();

        $this->service = new LogoutService($redis);
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
            $this->service->logout($refreshToken);
            return $this->json(['message' => 'Logged out successfully.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
