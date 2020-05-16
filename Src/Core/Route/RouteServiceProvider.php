<?php

namespace More\Src\Core\Route;


use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;

class RouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->app->bind('router', function ($method, $path) {
            return new Router($method, $path);
        });
    }
}