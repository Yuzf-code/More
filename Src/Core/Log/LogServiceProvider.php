<?php

namespace More\Src\Core\Log;


use More\Src\Core\Log\impl\Cli;
use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;

class LogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->app->singleton('logger', function () {
            $options = Config::getInstance()->get('app')['log'];
            return new Cli($options);
        });
    }
}