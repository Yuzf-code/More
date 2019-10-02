<?php

namespace More\Src\Lib\Session;


use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;
use More\Src\Lib\Session\impl\Redis as Session;

class SessionServiceProvider extends ServiceProvider
{
    public function boot()
    {

    }

    public function register()
    {
        $this->app->singleton('session', function () {
            $config = Config::getInstance()->get('app')['session'];
            return new Session($config);
        });
    }
}