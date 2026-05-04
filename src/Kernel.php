<?php

declare(strict_types=1);

namespace App;

use App\Config\DoctrineFactory;
use Doctrine\ORM\EntityManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Predis\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Kernel
{
    private EntityManager $em;
    private readonly Client $redis;
    private readonly RouteCollection $routes;
    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->em = DoctrineFactory::create();
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);
        $this->factory = new Psr17Factory();
        $this->routes = Router::routes();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->resetEntityManager();

        try {
            return $this->dispatch($request);
        } catch (\Throwable $e) {
            if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
                return $this->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
            }
            return $this->json(['error' => 'Internal server error'], 500);
        } finally {
            $this->em->clear();
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $context = new RequestContext('', $request->getMethod());
        $matcher = new UrlMatcher($this->routes, $context);

        try {
            $params = $matcher->match($request->getUri()->getPath());
        } catch (ResourceNotFoundException) {
            return $this->json(['error' => 'Not found'], 404);
        } catch (MethodNotAllowedException) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }

        $controllerClass = $params['_controller'];
        $controller = new $controllerClass($this->em, $this->redis);

        return $controller->handle($request, $params);
    }

    // Per-request reset: keeps the DB connection warm, clears stale entity state.
    private function resetEntityManager(): void
    {
        if (!$this->em->isOpen()) {
            $this->em = DoctrineFactory::create();
        } else {
            $this->em->clear();
        }
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        $body = $this->factory->createStream(json_encode($data, JSON_THROW_ON_ERROR));

        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
