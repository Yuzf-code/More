<?php

namespace Src\Core\WebSocket;


use Src\Core\Constant;
use Src\Core\ServiceProvider;

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
    }
}