<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\BaseController;
use App\Service\Auth\ResetPasswordService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ResetPasswordController extends BaseController
{
    private readonly ResetPasswordService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new ResetPasswordService($em, $redis);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $token    = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($token === '' || $password === '') {
            return $this->json(['error' => 'token and password are required.'], 400);
        }

        try {
            $this->service->reset($token, $password);
            return $this->json(['message' => 'Password reset successfully.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
