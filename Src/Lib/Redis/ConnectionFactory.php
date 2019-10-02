<?php

namespace More\Src\Lib\Redis;


use More\Src\Core\BaseInterface\Factory;

class ConnectionFactory implements Factory
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function make()
    {
        return new Connection($this->config);
    }
}