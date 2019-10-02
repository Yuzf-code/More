<?php
/**
 * Created by PhpStorm.
 * User: weeki
 * Date: 2019/5/2
 * Time: 23:38
 */

namespace Src\Lib\Pool;


use Src\Core\ServiceProvider;

class PoolServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->app->bind(Pool::class, function ($size, $factory) {
            return new Pool($size, $factory);
        });
    }
}