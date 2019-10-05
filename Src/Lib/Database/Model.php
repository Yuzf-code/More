<?php

namespace More\Src\Lib\Database;


use More\Src\Core\App;
use More\Src\Core\Constant;
use More\Src\Core\Http\Request as HTTPRequest;
use More\Src\Core\WebSocket\Command as WebsocketRequest;
use More\Src\Lib\Database\Relation\HasMany;
use More\Src\Lib\Database\Relation\HasOne;

/**
 * Class Model
 * @method static Builder where()
 * @method static Builder orWhere()
 * @method static Builder whereRaw($sql, $bindings = [])
 * @method static Builder orWhereRaw($sql, $bindings = [])
 * @method static mixed get($column = ['*'])
 * @method static mixed first($column = ['*'])
 * @method static Builder orderBy($field, $type)
 * @method static Builder groupBy(...$fields)
 * @method static int count($field, $alias = '')
 * @method static Builder join($table, $first, $operator = null, $second = null, $type = 'INNER')
 * @method static Builder leftJoin($table, $first, $operator = null, $second = null)
 * @method static Builder rightJoin($table, $first, $operator = null, $second = null)
 * @method static Builder with($relationship, array $column = ['*'], \Closure $helper = null)
 * @method static Builder take($row)
 * @method static Builder limit($start, $row)
 * @package More\Src\Lib\Database
 */
class Model implements \ArrayAccess, \JsonSerializable
{
    /**
     * 查询结果集返回类型
     */
    const RESULT_TYPE_ARRAY = 1;
    const RESULT_TYPE_MODEL = 2;

    /**
     * db实例
     * @var DB
     */
    protected $db;

    /**
     * 指定连接适配器
     * @var
     */
    protected $adapter;

    /**
     * 表名
     * @var
     */
    protected $table;

    /**
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 使用model对象返回
     * @var bool
     */
    protected $resultType = self::RESULT_TYPE_ARRAY;

    /**
     * 查询构造器
     * @var Builder
     */
    protected $builder;

    /**
     * 从请求加载数据时的字段映射
     * [
     *      $tableField => $request field
     * ]
     * @var array
     */
    protected $loadFromRequestFieldsMap = [];

    /**
     * 数据集
     * @var array
     */
    protected $data = [];

    public function __construct()
    {
        $this->db = App::getInstance()->db;

        if (!empty($this->adapter)) {
            $this->db->setAdapter($this->adapter);
        }

        $resultType = $this->getConfig('resultType');
        if (!empty($resultType)) {
            $this->resultType = $resultType;
        }
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * 获取查询构造器
     * @return Builder
     */
    public function newBuilder()
    {
        $builder = new Builder();
        $builder->setAdapter($this->adapter);
        $builder->setDb($this->db);
        $builder->setModel($this);
        $builder->setResultType($this->resultType);
        $builder->setTable($this->table);

        return $builder;
    }

    /**
     * 根据ID获取一条数据
     * @param $id
     * @param array $column
     * @return array|Model
     */
    public function find($id, $column = ['*'])
    {
        return $this->newBuilder()->where($this->primaryKey, $id)->first($column);
    }

    /**
     * 使用当前对象插入一条数据
     * @return bool
     */
    public function add()
    {
        if (empty($this->data)) {
            throw new \Exception('No properties set. Can not use add()');
        }

        $builder = $this->newBuilder();
        $success = $builder->insert($this->data);

        if ($success) {
            $this->data[$this->primaryKey] = $builder->lastInsertId($this->primaryKey);
        }

        return $success;
    }

    /**
     * 使用当前对象更新数据
     * @return mixed
     */
    public function save()
    {
        if (!isset($this->data[$this->primaryKey])) {
            throw new \Exception('No primary key set. Can not use save()');
        }

        return $this->newBuilder()->where($this->primaryKey, $this->data[$this->primaryKey])->update($this->data);
    }

    /**
     * 删除数据
     * @param null $id
     * @return mixed
     * @throws \Exception
     */
    public function delete($id = null)
    {
        $builder = $this->newBuilder();
        if (!is_null($id)) {
            $builder->where($this->primaryKey, $id);
        }

        return $builder->delete();
    }

    /**
     * 一对一关联
     * @param $related
     * @param $foreignKey
     * @param $localKey
     * @return HasOne
     */
    protected function hasOne($related, $foreignKey ,$localKey)
    {
        return new HasOne($related, $foreignKey, $localKey);
    }

    /**
     * 一对多关联
     * @param $related
     * @param $foreignKey
     * @param $localKey
     * @return HasMany
     */
    protected function hasMany($related, $foreignKey, $localKey)
    {
        return new HasMany($related, $foreignKey, $localKey);
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getClassName()
    {
        return static::class;
    }

    /**
     * 获取配置
     * @param string $key
     * @param null $default
     * @return null
     */
    public function getConfig($key = '', $default = null)
    {
        return $this->db->getConfig($key, $default);
    }

    /**
     * 从请求载入数据
     * 本来考虑过直接放在构造函数，可是突然想起犹豫服务端协议的多样性，项目可能会同时使用多种request
     * 所以做成了用户主动调用的形式
     * @param string $type
     * @return $this
     * @throws \Exception
     */
    public function loadFromRequest($type = Constant::HTTP_REQUEST)
    {
        if (empty($this->loadFromRequestFieldsMap)) {
            throw new \Exception('loadFromRequestFieldsMap can not be empty.');
        }

        $app = App::getInstance();
        switch ($type) {
            case Constant::HTTP_REQUEST:
                $request = $app->get(HTTPRequest::class);
                break;
            case Constant::WEBSOCKET_REQUEST:
                $request = $app->get(WebsocketRequest::class);
                break;
            default:
                throw new \Exception('Unknow type ' . $type);
        }

        foreach ($this->loadFromRequestFieldsMap as $tableFieldName => $requestFiledName) {
            // key存在时才载入
            if ($request->exist($requestFiledName)) {
                $this->data[$tableFieldName] = $request->get($requestFiledName);
            }
        }

        return $this;
    }

    /**
     * 动态调用查询构造器方法
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->newBuilder()->$method(...$parameters);
    }

    /**
     * 与__call方法配合，实现静态调用查询构造器方法
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function __toString()
    {
        return $this->toJson(JSON_UNESCAPED_UNICODE^JSON_UNESCAPED_SLASHES);
    }

    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            return null;
        }
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * key是否存在
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * 根据key获取对象
     * @param mixed $name
     * @return mixed
     * @throws \Exception
     */
    public function offsetGet($name)
    {
        return $this->data[$name];
    }

    /**
     * 快捷绑定
     * @param mixed $name
     * @param mixed $value
     */
    public function offsetSet($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * unset
     * @param mixed $name
     */
    public function offsetUnset($name)
    {
        unset($this->data[$name]);
    }
}