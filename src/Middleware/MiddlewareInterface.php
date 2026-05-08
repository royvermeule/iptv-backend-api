<?php

declare(strict_types=1);

namespace App\Middleware;

use Doctrine\ORM\EntityManager;
use Predis\Client;
use Psr\Http\Message\ServerRequestInterface;

interface MiddlewareInterface
{
    public function __construct(EntityManager $em, Client $redis);

    public function process(ServerRequestInterface $request): ServerRequestInterface;
}
