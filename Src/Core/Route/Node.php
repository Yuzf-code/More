<?php

namespace More\Src\Core\Route;


class Node
{
    const TYPE_STATIC = 0;
    const TYPE_ROOT = 1;
    const TYPE_PARAMS = 2;

    public $type = self::TYPE_STATIC;
    public $path = '';
    // 子节点是否为 params 节点
    public $paramsChild = false;
    // 索引，用于查找是快速选择下一个节点
    public $indices = '';
    public $child = [];
    public $handler = null;

    /**
     * 添加路由
     * @param $path
     * @param $handler
     * @throws \Exception
     */
    public function addRoute($path, $handler)
    {
        // 当前节点
        $n = $this;
        $paramsNum = $this->getParamsNum($path);

        if (empty($n->path)) {
            $n->insertChild($paramsNum, $path, $handler);
            return;
        }

        while (true) {
            $i = $n->getLongestCommonPrefix($path);

            // 边分裂
            // 从当前节点复制一个新的子节点
            if ($i != 0 && $i < strlen($n->path)) {
                $child = clone $n;
                $child->path = substr($n->path, $i);

                $n->path = substr($n->path, 0, $i);
                $n->child = [$child];
                $n->indices = $child->path[0];
            }

            // 还有剩下的路径需要处理
            if ($i < strlen($path)) {
                $path = substr($path, $i);

                // params节点处理
                if ($n->paramsChild) {
                    $n = $n->child[0];

                    if (strlen($path) >= strlen($n->path) && $n->path == substr($path, 0, strlen($n->path))) {
                        if ($path == $n->path || $path[strlen($n->path)] == '/') {
                            $paramsNum--;
                            continue;
                        }
                    }

                    throw new \Exception("Conflict between parameter '{$n->path}' and path '{$path}' already exists.");
                }

                // $path首字符，用于检索indices
                $c = $path[0];
                // 根据索引选择子节点继续向下处理
                for ($j = 0; $j < strlen($n->indices); $j++) {
                    if ($c == $n->indices[$j]) {
                        $n = $n->child[$j];
                        continue 2;
                    }
                }

                // 需要创建新节点
                if ($c != ':') {
                    $child = new Node();
                    $n->child[] = $child;
                    $n->indices .= $c;

                    $n = $child;
                }

                $n->insertChild($paramsNum, $path, $handler);
                return;
            }

            // 新加入的是一个已有节点
            $n->handler = $handler;
            return;
        }
    }

    /**
     * 计算路径参数数量
     * @param $path
     * @return int
     */
    public function getParamsNum($path)
    {
        $num = 0;

        for ($i = 0; $i < strlen($path); $i++) {
            if ($path[$i] == ':') {
                $num++;
            }
        }

        return $num;
    }

    /**
     * 将路径写入节点中
     * @param $paramsNum
     * @param $path
     * @param $handler
     * @throws \Exception
     */
    public function insertChild($paramsNum, $path, $handler)
    {
        $n = $this;

        while ($paramsNum > 0) {
            list('params' => $params, 'index' => $index) = $this->findParams($path);

            if ($index < 0) {
                // 没找到参数，可以直接结束循环
                // 将剩下的路径直接写入节点
                break;
            }

            // params ':'
            if ($params[0] == ':') {

                // 以params前的路径作为当前节点
                if ($index > 0) {
                    $n->path = substr($path, 0, $index);
                    $path = substr($path, $index);
                }

                // params作为独立子节点
                $child = new Node();
                $child->path = $params;
                $child->type = self::TYPE_PARAMS;

                $n->paramsChild = true;
                $n->child = [$child];

                $n = $child;
                $paramsNum--;

                // 还有剩下的路径需要处理
                if (strlen($params) < strlen($path)) {
                    $path = substr($path, strlen($params));
                    $child = new Node();
                    $n->child = [$child];
                    $n = $child;
                    continue;
                }

                $n->handler = $handler;
                return;
            }
        }

        $n->path = $path;
        $n->handler = $handler;
    }

    /**
     * 寻找路径中第一个params
     * @param $path
     * @return array
     * @throws \Exception
     */
    public function findParams($path)
    {
        $index = -1;
        $params = '';
        $len = strlen($path);

        for ($i = 0; $i < $len; $i++) {
            if ($path[$i] == ':') {
                $index = $i;
                $params .= $path[$i];

                for ($j = $i + 1; $j < $len; $j++) {
                    switch ($path[$j]) {
                        case '/':
                            // 找到一个完整的params
                            return compact('params', 'index');
                        case ':':
                            throw new \Exception('only one wildcard per path segment is allowed. Path: ' . $params);
                    }
                    $params .= $path[$j];
                }
            }
        }

        return compact('params', 'index');
    }

    /**
     * 获取最长相等前缀的长度
     * @param $path
     * @return int
     */
    public function getLongestCommonPrefix($path)
    {
        $i = 0;
        $len = min(strlen($path), strlen($this->path));

        while ($i < $len && $path[$i] == $this->path[$i]) {
            $i++;
        }

        return $i;
    }

    public function search($path) {
        $n = $this;
        $params = [];
        while (true) {
            if ($path == $n->path) {
                return $n->checkHandler($params);
            }

            // 继续向下检索
            if (strlen($path) > strlen($n->path) && substr($path, 0, strlen($n->path)) == $n->path) {
                $path = substr($path, strlen($n->path));

                if ($n->paramsChild) {
                    $n = $n->child[0];

                    $end = 0;
                    while ($end < strlen($path) && $path[$end] != '/') {
                        $end++;
                    }

                    $params[str_replace(':', '', $n->path)] = substr($path, 0, $end);

                    if ($end < $path) {
                        $path = substr($path, $end);

                        $n = $n->findNextChild($path);

                        if (empty($n)) {
                            return false;
                        } else {
                            continue;
                        }
                    }

                    // 匹配完成
                    return $n->checkHandler($params);
                }

                // static Node
                $n = $n->findNextChild($path);
                if (empty($n)) {
                    return false;
                } else {
                    continue;
                }
            }

            return false;
        }
    }

    protected function checkHandler($params)
    {
        if (is_null($this->handler)) {
            return false;
        } else {
            return [
                'handler' => $this->handler,
                'params' => $params
            ];
        }
    }

    public function findNextChild($path)
    {
        $c = $path[0];

        if ($this->paramsChild) {
            return $this->child[0];
        } else {
            for ($i = 0; $i < strlen($this->indices); $i++) {
                if ($c == $this->indices[$i]) {
                    return $this->child[$i];
                }
            }

            if (!empty($this->child)) {
                return $this->child[0];
            }

            return false;
        }
    }
}