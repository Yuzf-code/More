<?php

namespace More\Src\Core\WebSocket;


use More\Src\Core\Constant;
use More\Src\Core\ServiceProvider;
use More\Src\Core\WebSocket\Command as Request;

class WebSocketServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->app->singleton(Constant::WEBSOCKET_SERVER, function ($conf) {
            $server = new \swoole_websocket_server($conf['host'], $conf['port'], $conf['mode'], $conf['sockType']);
            $server->set($conf['setting']);
            return $server;
        });

        $this->app->singleton(Request::class, function () {
            return CommandContext::get(Constant::WEBSOCKET_REQUEST);
        });
    }
}