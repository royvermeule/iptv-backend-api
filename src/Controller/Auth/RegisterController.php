<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\RegistrationService;
use Doctrine\ORM\EntityManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RegisterController
{
    private readonly RegistrationService $service;
    private readonly Psr17Factory $factory;

    public function __construct(EntityManager $em, Client $redis)
    {
        $this->service = new RegistrationService($em, $redis);
        $this->factory = new Psr17Factory();
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $errors = $this->validate($email, $password);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], 422);
        }

        try {
            $this->service->register($email, $password);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json(
            ['message' => 'Registration successful. Please check your email to verify your account.'],
            201,
        );
    }

    private function validate(string $email, string $password): array
    {
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        return $errors;
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        $body = $this->factory->createStream(json_encode($data, JSON_THROW_ON_ERROR));

        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
