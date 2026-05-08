<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use App\Service\Profile\SelectProfileService;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SelectProfileController extends BaseController implements ControllerInterface
{
    private readonly SelectProfileService $service;

    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();

        $this->service = new SelectProfileService($em, $redis);
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $tokens = $this->service->select($this->getUserId($request), $params['id']);
            return $this->json($tokens);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode());
        }
    }
}
