<?php
/**
 * Created by PhpStorm.
 * User: weeki
 * Date: 2019/4/7
 * Time: 16:21
 */

namespace More\Src\Lib\Context;

use More\Src\Core\Swoole\Coroutine\Context\Context;

/**
 * Redis Coroutine Context Manager
 * Class RedisContext
 * @package Weekii\Core\Swoole\Coroutine\Context
 */
class RedisContext
{
    use Context;

    protected static $prefix = 'redis';
}