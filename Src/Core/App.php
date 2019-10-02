<?php
namespace More\Src\Core;

use More\Src\Core\Log\Logger;
use More\Src\Core\Swoole\ServerManager;
use More\Src\GlobalEvent;
use More\Src\Lib\Config;
use More\Src\Lib\Database\DB;
use More\Src\Lib\Redis\RedisManager;

/**
 * Class App
 * @property DB $db
 * @property RedisManager $redis
 * @property Logger $logger
 * @property ServerManager $serverManager
 * @package More\Src\Core
 */
class App extends Container
{
    protected $basePath;

    /**
     * 应用启动
     * @throws \Exception
     */
    public function run ($basePath = null)
    {

        $this->init($basePath);
        $this->serverManager->start();
    }

    /**
     * 应用初始化
     * @throws \Exception
     */
    private function init($basePath = null)
    {
        ini_set("display_errors","0");
        error_reporting(0);

        $this->errorHandle();

        \Swoole\Runtime::enableCoroutine();

        if ($basePath) {
            $this->setBasePath($basePath);
        }

        define('PROJECT_ROOT', $basePath);
        define('CONFIG_PATH', PROJECT_ROOT . '/Config');

        if (file_exists(PROJECT_ROOT . '/GlobalEvent.php')) {
            require_once PROJECT_ROOT . '/GlobalEvent.php';
        }

        // 注册服务提供者
        $this->registerServiceProviders();

        GlobalEvent::frameInit();

    }

    private function errorHandle()
    {
        if (isset($this[Constant::USER_ERROR_HANDLER]) && is_callable($this[Constant::USER_ERROR_HANDLER])) {
            $handler = $this[Constant::USER_ERROR_HANDLER];
        } else {
            $handler = function () {
                $error = error_get_last();
                $typeMap = array('1'=>'E_ERROR','2' => 'E_WARNING','4'=>'E_PARSE','8'=>'E_NOTICE','64'=>'E_COMPILE_ERROR');
                $type = $typeMap[$error['type']];
                $message = "ERRORS\WARNINGS\r\n \033[31mERROR:\033[37m: {$error['message']}[{$type}]\r\nSCRIPT: {$error['file']}\e[33m({$error['line']})\e[37m";
                $this->logger->error($message);
            };
        }
        register_shutdown_function($handler);
    }

    /**
     * 注册服务提供者
     * @throws \Exception
     */
    private function registerServiceProviders()
    {
        $providers = Config::getInstance()->get('app')['providers'];

        $boots = [];

        foreach ($providers as $providerClass) {
            if (class_exists($providerClass)) {
                $provider = new $providerClass($this);
                if ($provider instanceof ServiceProvider) {
                    $provider->register();
                    $boots[] = $provider;
                } else {
                    throw new \Exception('Class ' . $providerClass . 'must be extends ServiceProvider');
                }
            }
        }

        // TODO providers boot
        foreach ($boots as $boot) {
            $boot->boot();
        }
    }

    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        return $this;
    }
}