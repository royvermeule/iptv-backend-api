<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ControllerInterface
{
    public function __construct(EntityManager $em, Client $redis);

    public function handle(ServerRequestInterface $request, array $params): ResponseInterface;
}
