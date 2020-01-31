<?php

namespace More\Src\Core\WebSocket;


use More\Src\Core\Swoole\Coroutine\Context\Context;

class CommandContext
{
    use Context;

    protected static $prefix = 'WEBSOCKET_REQUEST';
}