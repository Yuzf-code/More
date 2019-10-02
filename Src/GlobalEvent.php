<?php
namespace Src;

use Src\Core\Http\Request;
use Src\Core\Http\Response;
use Src\Core\Swoole\EventRegister;
use Src\Core\WebSocket\Command;
use Src\Lib\Config;

class GlobalEvent
{
    public static function frameInit() {
        $conf = Config::getInstance()->get('app');
        if (isset($conf['timezone'])) {
            date_default_timezone_set($conf['timezone']);
        } else {
            date_default_timezone_set('Asia/Shanghai');
        }

        // your code
    }

    public static function serverCreate(\swoole_server $server, EventRegister $register) {

    }

    public static function onRequest(Request $request, Response $response) {

    }

    public static function afterAction(Request $request, Response $response) {

    }

    public static function onMessage(\swoole_server $server, Command $request)
    {

    }

    public static function afterMessage(\swoole_server $server, Command $request)
    {

    }
}