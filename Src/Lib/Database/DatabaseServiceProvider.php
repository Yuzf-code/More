<?php
/**
 * Created by PhpStorm.
 * User: weeki
 * Date: 2019/5/2
 * Time: 23:33
 */

namespace More\Src\Lib\Database;


use More\Src\Core\ServiceProvider;
use More\Src\Lib\Config;

class DatabaseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // TODO: Implement boot() method.
    }

    public function register()
    {
        $this->registerDatabaseService();
        $this->registerConnectionFactory();
    }

    protected function registerDatabaseService()
    {
        $this->app->singleton('db', function () {
            return new DB($this->app);
        });

        $this->app->singleton('db.config', function () {
            return Config::getInstance()->get('app')['database'];
        });
    }

    protected function registerConnectionFactory()
    {
        $this->app->bind(ConnectionFactory::class, function ($options) {
            return new ConnectionFactory($options);
        });
    }
}