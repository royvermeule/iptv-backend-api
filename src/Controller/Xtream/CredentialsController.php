<?php

declare(strict_types=1);

namespace App\Controller\Xtream;

use App\Controller\BaseController;
use App\Controller\ControllerInterface;
use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CredentialsController extends BaseController implements ControllerInterface
{
    public function __construct(EntityManager $em, Client $redis)
    {
        parent::__construct();
    }

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->json([
            'url'      => $request->getAttribute('xtream_url'),
            'username' => $request->getAttribute('xtream_username'),
            'password' => $request->getAttribute('xtream_password'),
        ]);
    }
}
