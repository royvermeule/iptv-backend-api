<?php

declare(strict_types=1);

namespace App\Controller\Sync;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Service\Xtream\SyncService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SyncController extends BaseController implements ControllerInterface
{
    private readonly SyncService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new SyncService();
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $json = $this->service->fetch(
            $request->getAttribute('xtream_url'),
            $request->getAttribute('xtream_username'),
            $request->getAttribute('xtream_password'),
        );

        return $this->rawJson($json);
    }
}
