<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Entity\Profile;
use App\Service\Profile\ProfileService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProfileController extends BaseController implements ControllerInterface
{
    private readonly ProfileService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new ProfileService($em);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET'    => $this->list($request),
            'POST'   => $this->create($request),
            'DELETE' => $this->delete($request, $params),
            default  => $this->json(['error' => 'Method not allowed'], 405),
        };
    }

    private function list(ServerRequestInterface $request): ResponseInterface
    {
        $profiles = $this->service->list($this->getUserId($request));

        return $this->json(array_map(fn(Profile $p) => [
            'id'              => $p->getId(),
            'name'            => $p->getName(),
            'country_code'    => $p->getCountryCode(),
            'has_credentials' => $p->hasCredentials(),
            'created_at'      => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $profiles));
    }

    private function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $name        = (string) ($data['name'] ?? '');
        $countryCode = isset($data['country_code']) ? (string) $data['country_code'] : null;

        if ($name === '') {
            return $this->json(['error' => 'name is required.'], 400);
        }

        try {
            $profile = $this->service->create($this->getUserId($request), $name, $countryCode);
            return $this->json([
                'id'           => $profile->getId(),
                'name'         => $profile->getName(),
                'country_code' => $profile->getCountryCode(),
                'created_at'   => $profile->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], 201);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    private function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->service->delete($this->getUserId($request), $params['id']);
            return $this->json(['message' => 'Profile deleted.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
