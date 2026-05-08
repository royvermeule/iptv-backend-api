<?php

declare(strict_types=1);

namespace App\Controller\Trending;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Service\Tmdb\TrendingService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TrendingController extends BaseController implements ControllerInterface
{
    private readonly TrendingService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new TrendingService($em, $redis);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $data = $this->service->getTrending($this->getProfileId($request));
            return $this->json($data);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
