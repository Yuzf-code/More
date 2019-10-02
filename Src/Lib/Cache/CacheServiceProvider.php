<?php

namespace Src\Lib\Cache;


use Src\Core\ServiceProvider;
use Src\Lib\Cache\impl\Redis as Cache;
use Src\Lib\Config;

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