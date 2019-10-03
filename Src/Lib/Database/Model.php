<?php

namespace More\Src\Lib\Database;


use More\Src\Core\App;
use More\Src\Lib\Database\Relation\HasMany;
use More\Src\Lib\Database\Relation\HasOne;

class Model implements \ArrayAccess
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
     * @return mixed
     */
    public function add()
    {
        return $this->newBuilder()->insert($this->data);
    }

    /**
     * 使用当前对象更新数据
     * @return mixed
     */
    public function save()
    {
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


    public function toJson($options = 0)
    {
        return json_encode($this->data, $options);
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

    public function __toString()
    {
        return $this->toJson();
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