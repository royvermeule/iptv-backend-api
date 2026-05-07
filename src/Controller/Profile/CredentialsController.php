<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Service\EncryptionService;
use App\Service\Profile\CredentialsService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CredentialsController extends BaseController implements ControllerInterface
{
    private readonly CredentialsService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new CredentialsService($em, new EncryptionService());
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $xtreamUrl      = (string) ($data['xtream_url'] ?? '');
        $xtreamUsername = (string) ($data['xtream_username'] ?? '');
        $xtreamPassword = (string) ($data['xtream_password'] ?? '');

        try {
            $this->validate($xtreamUrl, $xtreamUsername, $xtreamPassword);
            $this->service->store(
                userId: $this->getUserId($request),
                profileId: $params['id'],
                xtreamUrl: $xtreamUrl,
                xtreamUsername: $xtreamUsername,
                xtreamPassword: $xtreamPassword,
            );

            return $this->json(['message' => 'Credentials saved.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    private function validate(string $xtreamUrl, string $xtreamUsername, string $xtreamPassword): void
    {
        if ($xtreamUrl === '') {
            throw new \DomainException('Xtream URL cannot be empty', 422);
        }

        if ($xtreamUsername === '') {
            throw new \DomainException('Xtream username cannot be empty', 422);
        }

        if ($xtreamPassword === '') {
            throw new \DomainException('Xtream password cannot be empty', 422);
        }
    }
}
