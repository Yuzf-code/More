<?php

namespace Src\Core\Http;


use Src\Core\Constant;
use Src\Core\ServiceProvider;
use Src\Lib\Config;

class HttpServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method
    }

    public function register()
    {
        $this->registerServer();
    }



    protected function registerServer()
    {
        $this->app->singleton(Constant::HTTP_SERVER, function ($conf) {
            $server = new \swoole_http_server($conf['host'], $conf['port'], $conf['mode'], $conf['sockType']);
            $server->set($conf['setting']);
            return $server;
        });
    }
}