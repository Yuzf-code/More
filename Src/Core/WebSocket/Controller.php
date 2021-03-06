<?php

namespace More\Src\Core\WebSocket;


use More\Src\Core\App;
use More\Src\Core\Constant;

abstract class Controller
{
    protected $app;
    protected $request;
    protected $middlewares = [];

    /**
     * @var \swoole_websocket_server
     */
    protected $server;

    public function __construct(Command $request)
    {
        $this->app = App::getInstance();
        $this->server = $this->app->get(Constant::WEBSOCKET_SERVER);
        $this->request = $request;
    }

    protected function request()
    {
        return $this->request;
    }

    protected function getServer()
    {
        return $this->server;
    }

    protected function write($string) {
        $this->server->push($this->request->fd(), $string);
    }

    protected function writeJson(array $params)
    {
        $this->write(json_encode($params));
    }

    protected function actionNotFound($actionName)
    {
        $this->write("Action: " . $actionName . "Not Found.");
    }

    public function getMiddlewares()
    {
        return $this->middlewares;
    }
}