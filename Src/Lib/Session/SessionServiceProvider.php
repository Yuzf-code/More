<?php

namespace Src\Lib\Session;


use Src\Core\ServiceProvider;
use Src\Lib\Config;
use Src\Lib\Session\impl\Redis as Session;

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