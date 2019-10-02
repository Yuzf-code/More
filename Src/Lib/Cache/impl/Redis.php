<?php

namespace Src\Lib\Cache\impl;


use Src\Core\App;
use Src\Lib\Cache\Cache;

class Redis extends Cache
{
    protected $handler;

    public function __construct(array $options)
    {
        if (isset($options['expire']) && is_int($options['expire'])) {
            $this->expire = $options['expire'];
        }

        if (isset($options['prefix']) && is_string($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }

        $this->handler = App::getInstance()->redis;
    }

    public function set($key, $value, $expire = null)
    {
        $key = $this->withPrefix($key);
        $result = $this->handler->set($key, $value);

        if (is_null($expire)) {
            $expire = $this->expire;
        }

        if ($expire != 0) {
            $this->handler->expire($key, $expire);
        }

        return $result;
    }

    public function get($key)
    {
        $key = $this->withPrefix($key);
        return $this->handler->get($key);
    }

    public function del($key)
    {
        $key = $this->withPrefix($key);
        return $this->handler->delete($key);
    }
}