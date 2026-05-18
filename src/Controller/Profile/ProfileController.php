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

    public function handle(
        ServerRequestInterface $request,
        array $params,
    ): ResponseInterface {
        return match ($request->getMethod()) {
            "GET" => $this->list($request),
            "POST" => $this->create($request),
            "PATCH" => $this->update($request, $params),
            "DELETE" => $this->delete($request, $params),
            default => $this->json(["error" => "Method not allowed"], 405),
        };
    }

    private function list(ServerRequestInterface $request): ResponseInterface
    {
        $profiles = $this->service->list($this->getUserId($request));

        return $this->json(
            array_map(
                fn(Profile $p) => [
                    "id" => $p->getId(),
                    "name" => $p->getName(),
                    "country_code" => $p->getCountryCode(),
                    "has_credentials" => $p->hasCredentials(),
                    "has_pin" => $p->getPIn() !== null,
                    "created_at" => $p
                        ->getCreatedAt()
                        ->format(\DateTimeInterface::ATOM),
                ],
                $profiles,
            ),
        );
    }

    private function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(["error" => "Invalid JSON body"], 400);
        }

        $name = (string) ($data["name"] ?? "");
        $countryCode = isset($data["country_code"])
            ? (string) $data["country_code"]
            : null;

        $pin = isset($data["pin"]) ? (int) $data["pin"] : null;

        if ($name === "") {
            return $this->json(["error" => "name is required."], 400);
        }

        if ($pin !== null && strlen((string) $pin) !== 4) {
            return $this->json(["error" => "A pin must be 4 characters long"]);
        }

        try {
            $profile = $this->service->create(
                $this->getUserId($request),
                $name,
                $countryCode,
                $pin,
            );
            return $this->json(
                [
                    "id" => $profile->getId(),
                    "name" => $profile->getName(),
                    "country_code" => $profile->getCountryCode(),
                    "has_pin" => $profile->getPIn() !== null,
                    "created_at" => $profile
                        ->getCreatedAt()
                        ->format(\DateTimeInterface::ATOM),
                ],
                201,
            );
        } catch (\DomainException $e) {
            return $this->json(["error" => $e->getMessage()], $e->getCode());
        }
    }

    private function update(
        ServerRequestInterface $request,
        array $params,
    ): ResponseInterface {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(["error" => "Invalid JSON body"], 400);
        }

        $name = isset($data["name"]) ? (string) $data["name"] : null;
        $countryCode = array_key_exists("country_code", $data)
            ? (string) $data["country_code"]
            : null;

        $pinProvided = array_key_exists("pin", $data);
        $pin = $pinProvided ? ($data["pin"] !== null ? (int) $data["pin"] : null) : null;

        if ($name !== null && $name === "") {
            return $this->json(["error" => "name cannot be empty."], 400);
        }

        if ($pinProvided && $pin !== null && strlen((string) $pin) !== 4) {
            return $this->json(["error" => "A pin must be 4 characters long"], 400);
        }

        try {
            $profile = $this->service->update(
                $this->getUserId($request),
                $params["id"],
                $name,
                $countryCode,
                $pinProvided,
                $pin,
            );
            return $this->json([
                "id" => $profile->getId(),
                "name" => $profile->getName(),
                "country_code" => $profile->getCountryCode(),
                "has_credentials" => $profile->hasCredentials(),
                "has_pin" => $profile->getPIn() !== null,
                "created_at" => $profile
                    ->getCreatedAt()
                    ->format(\DateTimeInterface::ATOM),
            ]);
        } catch (\DomainException $e) {
            return $this->json(["error" => $e->getMessage()], $e->getCode());
        }
    }

    private function delete(
        ServerRequestInterface $request,
        array $params,
    ): ResponseInterface {
        try {
            $this->service->delete($this->getUserId($request), $params["id"]);
            return $this->json(["message" => "Profile deleted."]);
        } catch (\DomainException $e) {
            return $this->json(["error" => $e->getMessage()], $e->getCode());
        }
    }
}
