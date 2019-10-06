<?php

namespace More\Src\Lib\Database;

use More\Src\Core\App;
use More\Src\Lib\Util\Str;

/**
 * 数据库链接类
 * 包含基本CURD操作封装
 * Class Connection
 * @package Weekii\Lib\Database
 */
class Connection
{
    /**
     * 连接句柄
     * @var \PDO
     */
    protected $pdo;

    /**
     * 当前事务数
     * @var int
     */
    protected $transactions = 0;

    // 配置项
    protected $options;

    protected $logger;

    /**
     * 初始化链接
     * Connection constructor.
     */
    public function __construct($options)
    {
        $this->options = $options;
        $this->logger = App::getInstance()->logger;
        $this->connect();
    }

    /**
     * 创建连接
     */
    protected function connect()
    {
        $dsn = $this->options['driver'] . ':host=' . $this->options['host'] . ';dbname=' . $this->options['database'];
        $this->pdo = new \PDO($dsn, $this->options['username'], $this->options['password']);

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * 关闭链接
     * Close connection
     */
    public function close()
    {
        $this->pdo = null;
    }

    /**
     * 重连
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }

    /**
     * 获取原生PDO对象
     * @return \PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * 获取全部
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function select($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->getStatement($query, $bindings);

            $statement->execute();

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        });
    }

    /**
     * 获取一个
     * @param $query
     * @param array $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = [])
    {

        return array_shift($this->select($query, $bindings));
    }

    /**
     * @param $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->getStatement($query, $bindings);

            return $statement->execute();
        });
    }

    /**
     * 获取上次insert的id
     * @param null $name
     * @return string
     */
    public function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * @param $query
     * @param array $bindings
     * @return int 影响行数
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * @param $query
     * @param array $bindings
     * @return int 影响行数
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * 获取当前事务数
     * @return int
     */
    public function transactionLevel()
    {
        return $this->transactions;
    }

    /**
     * @param $name
     */
    public function savePoint($name)
    {
        $this->getPDO()->exec('SAVEPOINT ' . $name);
    }

    /**
     * @param $name
     */
    public function savePointRollBack($name)
    {
        $this->getPDO()->exec('ROLLBACK TO SAVEPOINT ' . $name);
    }

    /**
     * 开启事务，如果已开启则保存一个节点
     */
    public function beginTransaction()
    {
        if (!$this->getPDO()->inTransaction()) {
            try {
                // 开启事务
                $this->getPdo()->beginTransaction();
            } catch (\Exception $e) {
                $this->handleBeginTransactionException($e);
            }
        } elseif ($this->transactions >= 1) {
            // 已经有一个事务了，则在当前基础保存一个节点
            $this->savePoint('trans' . ($this->transactions + 1));
        }

        $this->transactions++;
    }

    /**
     * 提交事务
     */
    public function commit()
    {
        if ($this->transactions == 1) {
            $this->getPdo()->commit();
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    /**
     * 回滚
     * @param null $toLevel
     */
    public function rollBack($toLevel = null)
    {
        // toLevel为null则滚到上一个节点
        $toLevel = is_null($toLevel) ? $this->transactions - 1 : $toLevel;

        // 不可用的节点，直接return
        if ($toLevel < 0 || $toLevel >= $this->transactions) {
            return;
        }

        // 回到事务开始时
        if ($toLevel == 0) {
            $this->getPDO()->rollBack();
        } else {
            // 回滚到指定节点
            $this->savePointRollBack('trans' . ($toLevel + 1));
        }

        $this->transactions = $toLevel;
    }

    /**
     * 在事务中执行回调
     * @param \Closure $callback
     * @param int $attempts
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $callback();
                $this->commit();
                return;
            } catch (\Exception $e) {
                $this->handleTransactionException(
                    $e, $currentAttempt, $attempts
                );
            } catch (\Throwable $e) {
                $this->rollBack();

                throw $e;
            }
        }
    }

    protected function handleTransactionException($e, $currentAttempt, $maxAttempts)
    {
        if ($this->causedByDeadlock($e) && $this->transactions > 1) {
            $this->transactions--;
            throw $e;
        }

        $this->rollBack();

        if ($this->causedByDeadlock($e) && $currentAttempt < $maxAttempts) {
            return;
        }

        throw $e;
    }

    /**
     * 变量绑定
     * @param \PDOStatement $statement
     * @param $bindings
     */
    public function bindValues(\PDOStatement $statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
    }

    /**
     * 重置操作(将连接释放到连接池中时需要重置一下属性)
     */
    public function reset()
    {
        $this->transactions = 0;
    }

    /**
     * 查询预处理
     * @param $query
     * @param array $bindings
     * @return bool|\PDOStatement
     */
    public function getStatement($query, $bindings = [])
    {
        $statement = $this->pdo->prepare($query);
        $this->bindValues($statement, $this->prepareBindings($bindings));

        return $statement;
    }

    /**
     * 执行具有影响的操作
     * @param $query
     * @param array $bindings
     * @return mixed
     * @throws QueryException
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->getStatement($query, $bindings);

            $statement->execute();

            return $statement->rowCount();
        });
    }

    /**
     * 运行查询
     * @param $query
     * @param $bindings
     * @param \Closure $callback
     * @return mixed
     * @throws QueryException
     */
    protected function run($query, $bindings, \Closure $callback)
    {
        // 失去pdo连接对象，重连一下
        if (is_null($this->pdo)) {
            $this->connect();
        }

        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException($query, $bindings, $callback, $e);
        }

        return $result;
    }

    /**
     * 真正执行操作
     * @param $query
     * @param $bindings
     * @param \Closure $callback
     * @return mixed
     * @throws QueryException
     */
    protected function runQueryCallback($query, $bindings, \Closure $callback)
    {
        try {
            $result = $callback($query, $bindings);
            // 调试模式打印信息
            if (isset($this->options['debug']) && $this->options['debug'] === true) {
                $debugInfo = "SQL: " . $query . "\n";
                $debugInfo .= 'parameters: ' . json_encode($bindings, JSON_UNESCAPED_UNICODE) . "\n";
                $debugInfo .= 'rows: ' . count($result);
                $this->logger->debug($debugInfo);
            }
        } catch (\Exception $e) {
            throw new QueryException($query, $this->prepareBindings($bindings), $e);
        }

        return $result;
    }

    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * 错误处理
     * @param $sql
     * @param array $bindings
     * @param \Closure $callback
     * @param QueryException $e
     * @return mixed
     * @throws QueryException
     */
    protected function handleQueryException($sql, array $bindings, \Closure $callback, QueryException $e)
    {
        // 断线重连
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();
            return $this->runQueryCallback($sql, $bindings, $callback);
        }

        throw $e;
    }

    protected function handleBeginTransactionException($e)
    {
        if ($this->causedByLostConnection($e)) {
            $this->reconnect();

            $this->pdo->beginTransaction();
        } else {
            throw $e;
        }
    }

    /**
     * 是否由断线引发的异常
     * @param \Exception $e
     * @return bool
     */
    protected function causedByLostConnection(\Exception $e)
    {
        $message = $e->getMessage();

        return Str::contains($message, [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
        ]);
    }

    /**
     * 是否由deadlock引发的异常
     * @param \Exception $e
     * @return bool
     */
    protected function causedByDeadlock(\Exception $e)
    {
        $message = $e->getMessage();

        return Str::contains($message, [
            'Deadlock found when trying to get lock',
            'deadlock detected',
            'The database file is locked',
            'database is locked',
            'database table is locked',
            'A table in the database is locked',
            'has been chosen as the deadlock victim',
            'Lock wait timeout exceeded; try restarting transaction',
            'WSREP detected deadlock/conflict and aborted the transaction. Try restarting the transaction',
        ]);
    }
}