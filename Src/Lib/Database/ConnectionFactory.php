<?php
/**
 * Created by PhpStorm.
 * User: weeki
 * Date: 2019/5/2
 * Time: 23:13
 */

namespace Src\Lib\Database;


use Src\Core\BaseInterface\Factory;

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