<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\BaseController;
use App\Service\Auth\ForgotPasswordService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ForgotPasswordController extends BaseController
{
    private readonly ForgotPasswordService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new ForgotPasswordService($em, $redis);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $email = (string) ($data['email'] ?? '');

        if ($email === '') {
            return $this->json(['error' => 'email is required.'], 400);
        }

        // Always return the same response to avoid leaking whether an account exists.
        $this->service->handle($email);

        return $this->json(['message' => 'If that email is registered you will receive a reset link shortly.']);
    }
}
