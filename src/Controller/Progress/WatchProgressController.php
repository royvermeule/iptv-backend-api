<?php

declare(strict_types=1);

namespace App\Controller\Progress;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Entity\WatchProgress;
use App\Service\Progress\WatchProgressService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WatchProgressController extends BaseController implements ControllerInterface
{
    private readonly WatchProgressService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new WatchProgressService($em);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        if (isset($params['stream_id'])) {
            return match ($request->getMethod()) {
                'GET'  => $this->getOne($request, $params['stream_id']),
                'POST' => $this->upsert($request, $params['stream_id']),
                default => $this->json(['error' => 'Method not allowed'], 405),
            };
        }

        return $this->list($request);
    }

    private function list(ServerRequestInterface $request): ResponseInterface
    {
        $items = $this->service->listAll($this->getProfileId($request));

        return $this->json(array_map(fn(WatchProgress $p) => $this->format($p), $items));
    }

    private function getOne(ServerRequestInterface $request, string $streamId): ResponseInterface
    {
        $progress = $this->service->getOne($this->getProfileId($request), $streamId);

        if (!$progress) {
            return $this->json(['error' => 'Progress not found'], 404);
        }

        return $this->json($this->format($progress));
    }

    private function upsert(ServerRequestInterface $request, string $streamId): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $streamType = (string) ($data['stream_type'] ?? '');

        if (!isset($data['timestamp_seconds'])) {
            return $this->json(['error' => 'timestamp_seconds is required'], 422);
        }

        $timestampSeconds = (int) $data['timestamp_seconds'];

        if ($timestampSeconds < 0) {
            return $this->json(['error' => 'timestamp_seconds must be a non-negative integer'], 422);
        }

        try {
            $this->service->upsert($this->getProfileId($request), $streamId, $streamType, $timestampSeconds);
            return $this->json(['message' => 'Progress saved.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    private function format(WatchProgress $p): array
    {
        return [
            'stream_id'         => $p->getStreamId(),
            'stream_type'       => $p->getStreamType(),
            'timestamp_seconds' => $p->getTimestampSeconds(),
            'updated_at'        => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
