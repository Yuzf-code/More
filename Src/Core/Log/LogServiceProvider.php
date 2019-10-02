<?php

namespace Src\Core\Log;


use Src\Core\Log\impl\Cli;
use Src\Core\ServiceProvider;
use Src\Lib\Config;

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