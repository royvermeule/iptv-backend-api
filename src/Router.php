<?php

declare(strict_types=1);

namespace App;

use App\Controller\Auth\ForgotPasswordController;
use App\Controller\Auth\LoginController;
use App\Controller\Auth\LogoutController;
use App\Controller\Auth\RefreshController;
use App\Controller\Auth\RegisterController;
use App\Controller\Auth\ResetPasswordController;
use App\Controller\Auth\VerifyEmailController;
use App\Controller\Profile\CredentialsController;
use App\Controller\Profile\ProfileController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    private readonly RouteCollection $routes;

    public function __construct()
    {
        $this->routes = new RouteCollection();
        $this->auth();
        $this->profiles();
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    private function profiles(): void
    {
        $this->routes->add(
            name: 'profiles',
            route: new Route(
                path: '/api/profiles',
                defaults: ['_controller' => ProfileController::class],
                methods: ['GET', 'POST']
            )
        );
        $this->routes->add(
            name: 'profile_item',
            route: new Route(
                path: '/api/profiles/{id}',
                defaults: ['_controller' => ProfileController::class],
                methods: ['PATCH', 'DELETE']
            )
        );
        $this->routes->add(
            name: 'profile_credentials',
            route: new Route(
                path: '/api/profiles/{id}/credentials',
                defaults: ['_controller' => CredentialsController::class],
                methods: ['POST']
            )
        );
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
        $this->routes->add(
            name: 'auth_forgot_password',
            route: new Route(
                path: '/api/auth/forgot-password',
                defaults: ['_controller' => ForgotPasswordController::class],
                methods: ['POST']
            )
        );
        $this->routes->add(
            name: 'auth_reset_password',
            route: new Route(
                path: '/api/auth/reset-password',
                defaults: ['_controller' => ResetPasswordController::class],
                methods: ['POST']
            )
        );
    }
}
