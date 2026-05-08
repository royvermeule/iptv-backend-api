<?php

declare(strict_types=1);

namespace App\Controller\Favorites;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Entity\Favorite;
use App\Service\Favorites\FavoritesService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FavoritesController extends BaseController implements ControllerInterface
{
    private readonly FavoritesService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new FavoritesService($em);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET'    => $this->list($request),
            'POST'   => $this->add($request),
            'DELETE' => $this->remove($request, $params['stream_id']),
            default  => $this->json(['error' => 'Method not allowed'], 405),
        };
    }

    private function list(ServerRequestInterface $request): ResponseInterface
    {
        $favorites = $this->service->list($this->getProfileId($request));

        return $this->json(array_map(fn(Favorite $f) => $this->format($f), $favorites));
    }

    private function add(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $streamId   = (string) ($data['stream_id'] ?? '');
        $streamType = (string) ($data['stream_type'] ?? '');

        if ($streamId === '') {
            return $this->json(['error' => 'stream_id is required'], 422);
        }

        try {
            $favorite = $this->service->add($this->getProfileId($request), $streamId, $streamType);
            return $this->json($this->format($favorite), 201);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    private function remove(ServerRequestInterface $request, string $streamId): ResponseInterface
    {
        try {
            $this->service->remove($this->getProfileId($request), $streamId);
            return $this->json(['message' => 'Removed from favorites.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    private function format(Favorite $f): array
    {
        return [
            'stream_id'   => $f->getStreamId(),
            'stream_type' => $f->getStreamType(),
            'created_at'  => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
