<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/15
 * Time: 19:37
 */

namespace More\Src\Core\Route;


use More\Src\Core\App;
use More\Src\Lib\Config;

class RouteRule
{
    const FOUND = 1;
    const NOT_FOUND = 2;

    // 是否需要初始化
    protected static $needInit = true;
    private static $rule = [];
    protected static $trees = [];

    // 导入路由规则
    public static function init()
    {
        if (self::$needInit) {

            $directory =PROJECT_ROOT . '/App/Routes/';
            $file = scandir($directory);
            foreach ($file as $item) {
                // 去除两个特殊目录
                if (in_array($item, ['.', '..'])) {
                    continue;
                }
                require_once $directory . $item;
            }
            self::$needInit = false;
        }
    }

    public static function runRule($method, $path)
    {
        // 先尝试匹配当前方法的规则
        $result = self::runRuleGroup($method, $path);

        // 没匹配的话尝试匹配支持所有方法的规则
        if (empty($result)) {
            $result = self::runRuleGroup('*', $path);
        }

        // 规则匹配成功
        if (!empty($result)) {
            return $result;
        }

        // 匹配失败直接返回
        return [
            'status' => self::NOT_FOUND,
            'target' => $path,
            'args' => null
        ];
    }

    public static function runRuleGroup($method, $path)
    {
        if (empty(self::$trees[$method])) {
            return false;
        }

        $root = self::$trees[$method];

        $result = $root->search($path);

        if ($result !== false) {
            return [
                'status' => self::FOUND,
                'target' => $result['handler'],
                'args' => $result['params']
            ];
        }

        /*$pathSlice = explode('/', $path);

        foreach (self::$rule[$method] as $rule) {
            $patternSlice = explode('/', $rule['pattern']);

            $args = [];
            foreach ($patternSlice as $index => $item) {
                // 路由变量
                if (strpos($item, ':') !== false) {
                    if (isset($pathSlice[$index])) {
                        // 路由变量匹配成功，直接跳过，继续向下解析
                        $args[str_replace(':', '', $item)] = $pathSlice[$index];
                        continue;
                    } else {
                        // 路由变量解析失败，跳过此规则
                        continue 2;
                    }
                }

                // 不匹配则跳过此规则
                if (!isset($pathSlice[$index]) || $item !== $pathSlice[$index]) {
                    continue 2;
                }
            }



            // 当前规则解析完成
            return [
                'status' => self::FOUND,
                'target' => $rule['target'],
                'args' => $args
            ];
        }*/
        // 没有匹配规则
        return false;
    }

    /**
     * 添加路由规则
     * @param $pattern
     * @param $target
     * @param string $method
     * @throws \Exception
     */
    public static function rule($pattern, $target, $method = '*')
    {
        if (isset(self::$trees[$method])) {
            $root = self::$trees[$method];
        } else {
            $root = new Node();
            $root->type = Node::TYPE_ROOT;
            self::$trees[$method] = $root;
        }

        $root->addRoute($pattern, $target);
    }

    /**
     * 添加 GET 方法路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function get($pattern, $target)
    {
        self::rule($pattern, $target, 'GET');
    }

    /**
     * 添加 POST 方法路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function post($pattern, $target)
    {
        self::rule($pattern, $target, 'POST');
    }

    /**
     * 添加 PUT 方法路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function put($pattern, $target)
    {
        self::rule($pattern, $target, 'PUT');
    }

    /**
     * 添加 DELETE 方法路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function delete($pattern, $target)
    {
        self::rule($pattern, $target, 'DELETE');
    }

    /**
     * 添加 PATCH 方法路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function patch($pattern, $target)
    {
        self::rule($pattern, $target, 'PATCH');
    }

    /**
     * 添加普通路由
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function any($pattern, $target)
    {
        self::rule($pattern, $target, '*');
    }

    /**
     * 消息路由（websocket tcp 等长连接协议使用）
     * @param $pattern
     * @param $target
     * @throws \Exception
     */
    public static function message($pattern, $target)
    {
        self::rule($pattern, $target, 'MESSAGE');
    }
}