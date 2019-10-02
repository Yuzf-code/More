<?php

namespace More\Src\Lib\Cache;


use More\Src\Core\ServiceProvider;
use More\Src\Lib\Cache\impl\Redis as Cache;
use More\Src\Lib\Config;

class CacheServiceProvider extends ServiceProvider
{
    public function boot()
    {

    }

    public function register()
    {
        $this->app->singleton('cache', function () {
            $options = Config::getInstance()->get('app')['cache'];
            return new Cache($options);
        });
    }
}