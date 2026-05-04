<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\BaseController;
use App\Service\Auth\VerifyEmailService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class VerifyEmailController extends BaseController
{
    private readonly VerifyEmailService $service;

    public function __construct(EntityManager $em, Client $_redis)
    {
        parent::__construct();

        $this->service = new VerifyEmailService($em);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $token = (string) ($request->getQueryParams()['token'] ?? '');

        if ($token === '') {
            return $this->json(['error' => 'Token is required.'], 400);
        }

        try {
            $this->service->verify($token);
            return $this->json(['message' => 'Email verified successfully.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
