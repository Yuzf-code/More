<?php

namespace Src\Core\Swoole;


use Src\Core\ServiceProvider;

class ServerManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->app->singleton('serverManager', function () {
            return new ServerManager();
        });
    }
}