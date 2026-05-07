<?php

declare(strict_types=1);

namespace App;

use App\Controller\Auth\LoginController;
use App\Controller\Auth\LogoutController;
use App\Controller\Auth\RefreshController;
use App\Controller\Auth\RegisterController;
use App\Controller\Auth\VerifyEmailController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    private readonly RouteCollection $routes;

    public function __construct()
    {
        $this->routes = new RouteCollection();
        $this->auth();
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    private function auth(): void
    {
        $this->routes->add(
            name: 'auth_register',
            route: new Route(
                path: '/api/auth/register',
                defaults: ['_controller' => RegisterController::class],
                methods: ['POST']
            )
        );
        $this->routes->add(
            name: 'auth_login',
            route: new Route(
                path: '/api/auth/login',
                defaults: ['_controller' => LoginController::class],
                methods: ['POST']
            )
        );
        $this->routes->add(
            name: 'auth_verify_email',
            route: new Route(
                path: '/api/auth/verify-email',
                defaults: ['_controller' => VerifyEmailController::class],
                methods: ['GET']
            )
        );
        $this->routes->add(
            name: 'auth_refresh',
            route: new Route(
                path: '/api/auth/refresh',
                defaults: ['_controller' => RefreshController::class],
                methods: ['POST']
            )
        );
        $this->routes->add(
            name: 'auth_logout',
            route: new Route(
                path: '/api/auth/logout',
                defaults: ['_controller' => LogoutController::class],
                methods: ['POST']
            )
        );
    }
}
