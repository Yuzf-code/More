<?php

namespace More\Src\Lib\Redis;


use More\Src\Core\App;
use More\Src\Lib\Context\RedisContext;
use More\Src\Lib\Pool\Pool;

class RedisManager
{
    const CONNECTION = 'connection';

    protected $config;

    /**
     * @var Pool
     */
    protected $pool;

    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = $this->app->get('redis.config');

        $poolSize = $this->getConfig('poolSize');
        $connectionFactory = $this->app->make(ConnectionFactory::class, [$this->config]);

        $this->pool = $this->app->make(Pool::class, [$poolSize, $connectionFactory]);
    }

    public function getConfig($key = '', $default = null)
    {
        if (empty($key)) {
            $config =  $this->config;
        } else {
            $config = $this->config[$key];
        }

        if (!is_null($default) && is_null($config)) {
            $config = $default;
        }

        return $config;
    }

    /**
     * 获取连接对象
     * @return Connection
     * @throws ConnectionException
     * @throws \More\Src\Core\Swoole\Coroutine\CoroutineExcepiton
     */
    public function getConnection():Connection
    {
        $connection = RedisContext::get(self::CONNECTION);

        // 当前协程未获取连接
        if (empty($connection)) {
            // 从连接池里拿一个
            $connection = $this->pool->pop($this->getConfig('getConnectionTimeout', 1));

            if (empty($connection)) {
                throw new ConnectionException("Getting connection timeout from pool.", 100);
            }

            // 保存一下
            RedisContext::set(self::CONNECTION, $connection);
        }

        return $connection;
    }

    /**
     * 回收连接
     * @throws \More\Src\Core\Swoole\Coroutine\CoroutineExcepiton
     */
    public function freeConnection()
    {
        $connection = RedisContext::get(self::CONNECTION);

        if (!empty($connection)) {
            $this->pool->push($connection);

            RedisContext::delete();
        }
    }

    /**
     * 动态调用
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws ConnectionException
     * @throws \More\Src\Core\Swoole\Coroutine\CoroutineExcepiton
     */
    public function __call($method, $arguments)
    {
        return $this->getConnection()->$method(...$arguments);
    }
}