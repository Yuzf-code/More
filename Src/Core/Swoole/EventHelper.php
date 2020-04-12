<?php
namespace More\Src\Core\Swoole;


use duncan3dc\Laravel\BladeInstance;
use More\Src\Core\App;
use More\Src\Core\Constant;
use More\Src\Core\Http\Dispatcher;
use More\Src\Core\Http\Request;
use More\Src\Core\Http\RequestContext;
use More\Src\Core\Http\Response;
use More\Src\Core\WebSocket\Command;
use More\Src\Core\WebSocket\CommandContext;
use More\Src\Core\WebSocket\DispatchException;
use More\Src\GlobalEvent;
use More\Src\Lib\Config;
use More\Src\Lib\Pipeline\Pipeline;

class EventHelper
{
    /**
     * 注册默认的http路由调度
     * @param App $app
     * @param EventRegister $register
     * @param string $controllerNameSpace
     * @throws \Exception
     */
    public static function registerDefaultOnRequest(EventRegister $register, $controllerNameSpace = 'App\\Http\\Controller\\')
    {
        // 注册request回调
        $app = App::getInstance();
        $dispatcher = new Dispatcher(App::getInstance(), $controllerNameSpace);

        // 获取全局中间件组
        if ($app->offsetExists(Constant::GLOBAL_MIDDLEWARES)) {
            $middlewares = $app->get(Constant::GLOBAL_MIDDLEWARES);
        } else {
            $middlewares = [];
        }


        $register->set($register::onRequest, function (\swoole_http_request $swooleRequest, \swoole_http_response $swooleResponse) use ($dispatcher, $app, $middlewares) {
            $request = new Request($swooleRequest);
            $response = new Response($swooleResponse);
            $view = new BladeInstance(PROJECT_ROOT . '/App/View', Config::getInstance()->get('app')['tempDir'] . '/templates');

            RequestContext::set(Constant::HTTP_REQUEST, $request);

            try {
                GlobalEvent::onRequest($request, $response);

                // 请求通过全局中间件
                (new Pipeline($app))->send($request)
                    ->trough($middlewares)
                    ->then(function ($request) use ($dispatcher, $response, $view) {
                        $dispatcher->dispatch($request, $response, $view);
                    });

                GlobalEvent::afterAction($request, $response);
            } catch (\Throwable $e) {
                if (isset($app[Constant::HTTP_REQUEST_EXCEPTION_HANDLER]) && is_callable($app[Constant::HTTP_REQUEST_EXCEPTION_HANDLER])) {
                    call_user_func($app[Constant::HTTP_REQUEST_EXCEPTION_HANDLER], $e, $request, $response, $view);
                } else {
                    $app->logger->throwable($e);
                }
            }

            // 释放连接
            $app->db->freeConnection();
            $app->redis->freeConnection();
            // 清空请求上下文保存的对象
            RequestContext::delete();
        });
    }

    /**
     * 注册默认websocket消息路由调度
     * @param App $app
     * @param EventRegister $register
     */
    public static function registerDefaultOnMessage(EventRegister $register)
    {
        $app = App::getInstance();
        $register->set($register::onMessage, function (\swoole_websocket_server $server, \swoole_websocket_frame $frame) use ($app) {
            $request = new Command($frame);

            try {
                GlobalEvent::onMessage($server, $request);

                if ($request->hasControllerAction()) {
                    $controllerNamespace = $request->getControllerNamespace();
                    if (class_exists($controllerNamespace)) {
                        $obj = new $controllerNamespace($request);
                        $actionName = $request->getActionName();
                        if (method_exists($obj, $actionName)) {
                            //$obj->$actionName();
                            $app->call([$obj, $actionName]);
                        } else {
                            $obj->actionNotFound();
                        }
                    } else {
                        throw new DispatchException('Controller not found');
                    }
                } else {
                    throw new DispatchException('Can not dispatch to controller. because Request does not set $controllerNameSpace or $actionName');
                }

                GlobalEvent::afterMessage($server, $request);
            } catch (\Throwable $e) {
                if (isset($app[Constant::WEBSOCKET_MESSAGE_EXCEPTION_HANDLER]) && is_callable($app[Constant::WEBSOCKET_MESSAGE_EXCEPTION_HANDLER])) {
                    call_user_func($app[Constant::WEBSOCKET_MESSAGE_EXCEPTION_HANDLER], $e, $request, $server);
                } else {
                    $app->logger->throwable($e);
                }
            }

            // 释放连接
            $app->db->freeConnection();
            $app->redis->freeConnection();
            // 清空请求上下文保存的对象
            CommandContext::delete();
        });
    }

    /**
     * 注册用户自定义错误处理
     * @param callable $handler
     */
    public static function registerErrorHandler(callable $handler)
    {
        App::getInstance()->instance(Constant::USER_ERROR_HANDLER, $handler);
    }

    /**
     * 注册用户自定义http dispatch 时异常handler
     * @param \Closure $handler
     */
    public static function registerOnRequestExceptionHandler(callable $handler)
    {
        App::getInstance()->instance(Constant::HTTP_REQUEST_EXCEPTION_HANDLER, $handler);
    }

    /**
     * 注册用户自定义websocket dispatch 时异常handler
     * @param \Closure $handler
     */
    public static function registerOnMessageExceptionHandler(callable $handler)
    {
        App::getInstance()->instance(Constant::WEBSOCKET_MESSAGE_EXCEPTION_HANDLER, $handler);
    }
}