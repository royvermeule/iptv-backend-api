<?php

declare(strict_types=1);

namespace App;

use App\Controller\Auth\LoginController;
use App\Controller\Auth\RegisterController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    public static function routes(): RouteCollection
    {
        $routes = new RouteCollection();

        // Auth
        $routes->add('auth_register', new Route('/api/auth/register', ['_controller' => RegisterController::class], methods: ['POST']));
        $routes->add('auth_login',    new Route('/api/auth/login',    ['_controller' => LoginController::class],    methods: ['POST']));

        return $routes;
    }
}
