<?php

namespace More\Src\Core\Route;

class Router
{
    private $method;
    private $requestPath;

    public function __construct($method, $path)
    {
        $this->method = $method;
        $this->requestPath = $path;
    }

    public function dispatch()
    {
        return RouteRule::runRule($this->method, $this->requestPath);
    }
}