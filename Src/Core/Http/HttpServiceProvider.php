<?php

namespace More\Src\Core\Http;


use More\Src\Core\Constant;
use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;

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

        $this->app->bind(Request::class, function () {
            return RequestContext::get(Constant::HTTP_REQUEST);
        });
    }
}