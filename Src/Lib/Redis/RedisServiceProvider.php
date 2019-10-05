<?php

namespace More\Src\Lib\Redis;


use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;

class RedisServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(ConnectionFactory::class, function ($config) {
            return new ConnectionFactory($config);
        });

        $this->app->singleton('redis', function () {
            return new RedisManager($this->app);
        });

        $this->app->singleton('redis.config', function () {
            return Config::getInstance()->get('app')['redis'];
        });
    }

    public function boot()
    {
        // TODO: Implement boot() method.
    }
}