<?php

namespace More\Src\Core\Http;


use More\Src\Core\Swoole\Coroutine\Context\Context;

class RequestContext extends Context
{
    protected static $prefix = 'HTTP_REQUEST';
}