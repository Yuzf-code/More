<?php

namespace More\Src\Lib\Database;


use More\Src\Core\App;
use More\Src\Lib\Database\Relation\Relation;

class Builder
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
     * 模型实例
     * @var Model
     */
    protected $model;

    /**
     * 使用model对象返回
     * @var bool
     */
    protected $resultType = self::RESULT_TYPE_ARRAY;

    /**
     * join语句参数
     * @var array
     */
    protected $joins = [];

    /**
     * 条件表达式数组
     * @var array
     */
    protected $conditions = [];

    /**
     * 参数绑定
     * @var array
     */
    protected $bindings = [];

    /**
     * limit参数
     * @var array
     */
    protected $limit = [];

    /**
     * orderBy参数
     * @var array
     */
    protected $orderBy = [];

    /**
     * groupBy参数
     * @var array
     */
    protected $groupBy = [];

    /**
     * 模型关联
     * @var array
     */
    protected $relationships = [];

    public function setModel(Model $model)
    {
        $this->model = $model;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setResultType($resultType)
    {
        $this->resultType = $resultType;
    }

    public function getResultType()
    {
        return $this->resultType;
    }

    /**
     * @return DB
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * @param DB $db
     */
    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * @return mixed
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param mixed $adapter
     */
    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param mixed $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * 获取表名
     * @return string
     */
    public function getTableName()
    {
        if ($this->table == null) {
            throw new \Exception('Table Can not be null.');
        }

        return $this->db->tableName($this->table);
    }

    /**
     * AND条件表达式
     * @return $this
     */
    public function where()
    {
        $paramsNum = func_num_args();
        $params = func_get_args();

        if ($paramsNum == 2) {
            $this->addCondition('AND', $this->newPattern($params[0], '=', $params[1]) );
        } elseif ($paramsNum == 3) {
            $this->addCondition('AND', $this->newPattern($params[0], $params[1], $params[2]));
        }

        return $this;
    }

    /**
     * OR条件表达式
     * @return $this
     */
    public function orWhere()
    {
        $paramsNum = func_num_args();
        $params = func_get_args();

        if ($paramsNum == 2) {
            $this->addCondition('OR', $this->newPattern($params[0], '=', $params[1]) );
        } elseif ($paramsNum == 3) {
            $this->addCondition('OR', $this->newPattern($params[0], $params[1], $params[2]));
        }

        return $this;
    }

    /**
     * 原生sql表达式条件
     * @param $sql
     * @param array $bindings
     * @return $this
     */
    public function whereRaw($sql, $bindings = [])
    {
        $this->addCondition('AND', [$sql, $bindings]);

        return $this;
    }

    /**
     * 原生or条件
     * @param $sql
     * @param array $bindings
     * @return $this
     */
    public function orWhereRaw($sql, $bindings = [])
    {
        $this->addCondition('OR', [$sql, $bindings]);

        return $this;
    }

    protected function getPrimaryKey()
    {
        if (empty($this->getModel())) {
            throw new \Exception('Model is null. Can not get primaryKey.');
        }

        return $this->getModel()->getPrimaryKey();
    }



    /**
     * 获取多条
     * @param array $column
     * @return mixed
     */
    public function get($column = ['*'])
    {
        return $this->run($this->generateSelectSQL($column), $this->bindings, function ($sql, $bindings) {
            $result =  $this->db->select($sql, $bindings);

            // 处理结果集
            foreach ($result as $index => $item) {
                // 使用model返回
                if ($this->resultType == self::RESULT_TYPE_MODEL) {
                    // 结果集转换为为model对象
                    $result[$index] = $this->resultToModel($item);
                }

                $this->loadRelationship($result[$index]);
            }

            return $result;
        });
    }

    /**
     * 获取一条
     * @param array $column
     * @return Model | array
     */
    public function first($column = ['*'])
    {
        $this->take(1);

        $data = $this->run($this->generateSelectSQL($column), $this->bindings, function ($sql, $bindings) {
            return $this->db->selectOne($sql, $bindings);
        });

        // handle relationship
        $this->loadRelationship($data);

        if ($this->resultType == self::RESULT_TYPE_MODEL) {
            $this->getModel()->setData($data);
            return $this->getModel();
        } else {
            return $data;
        }
    }

    /**
     * 插入数据
     * @param array $data
     * @return mixed
     */
    public function insert(array $data)
    {
        return $this->run($this->generateInsertSQL($data), $this->bindings, function ($sql, $bindings) {
            return $this->db->insert($sql, $bindings);
        });
    }

    /**
     * 更新数据
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        return $this->run($this->generateUpdateSQL($data), $this->bindings, function ($sql, $bindings) {
            return $this->db->update($sql, $bindings);
        });
    }

    /**
     * 删除数据
     * @param null $id
     * @return mixed
     * @throws \Exception
     */
    public function delete()
    {
        if (empty($this->conditions)) {
            throw new \Exception('Method delete() must has conditions');
        }

        return $this->run($this->generateDeleteSQL(), $this->bindings, function ($sql, $bindings) {
            return $this->db->delete($sql, $bindings);
        });
    }

    /**
     * 排序
     * @param $field
     * @param $type
     */
    public function orderBy($field, $type)
    {
        $this->orderBy[] = compact('field', 'type');
        return $this;
    }

    /**
     * 分组
     * @param mixed ...$fields
     */
    public function groupBy(...$fields)
    {
        $this->groupBy = $fields;
        return $this;
    }

    /**
     * count
     * @param $field
     * @param string $alias
     * @return int
     */
    public function count($field, $alias = '')
    {
        $field = 'COUNT(' . $field . ')';

        if (!empty($alias)) {
            $field .= ' AS ' . $alias;
        } else {
            $alias = $field;
        }

        $result = $this->run($this->generateSelectSQL([$field]), $this->bindings, function ($sql, $bindings) {
            return $this->db->selectOne($sql, $bindings);
        });

        return $result[$alias];
    }

    /**
     * join操作，默认为inner join
     * @param $table
     * @param string $first
     * @param null|array|string $operator
     * @param null|string $second
     * @param string $type
     * @throws \Exception
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'INNER')
    {

        $builder = new static;

        if ($first instanceof \Closure) {
            call_user_func($first, $builder);
        } else {
            $builder->where($first, $operator, $second);
        }

        $this->joins[] = [
            'builder' => $builder,
            'table' => $table,
            'type' => $type
        ];

        return $this;
    }

    /**
     * left join
     * @param $table
     * @param $first
     * @param null $operator
     * @param null $second
     * @return Builder
     * @throws \Exception
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator = null, $second = null, 'LEFT');
    }

    /**
     * right join
     * @param $table
     * @param $first
     * @param null $operator
     * @param null $second
     * @return Builder
     * @throws \Exception
     */
    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator = null, $second = null, 'RIGHT');
    }

    /**
     * 注册关联模型方法
     * @param $relationship
     * @param array $column
     * @param \Closure|null $helper
     * @return $this
     */
    public function with($relationship, array $column = ['*'], \Closure $helper = null)
    {
        if (!method_exists($this, $relationship)) {
            throw new \Exception("relationship method not found.");
        }

        $relation = $this->$relationship();

        if (!$relation instanceof Relation) {
            throw new \Exception("relationship method must return an Relation instance.");
        }

        $this->addRelationship($relationship, $relation, $column, $helper);

        return $this;
    }

    /**
     * 添加关联关系
     * @param $relationship
     * @param Relation $relation
     * @param array $column
     */
    public function addRelationship($relationship, Relation $relation, array $column = ['*'], \Closure $helper = null)
    {
        $this->relationships[$relationship] = compact('relation', 'column', 'helper');
    }

    /**
     * 挂载关联模型数据
     * @param array | Model $row
     */
    protected function loadRelationship(&$row)
    {
        foreach ($this->relationships as $key => $item) {
            $row[$key] = $item['relation']->getResult($row, $item['column'], $item['helper']);
        }
    }

    /**
     * 获取指定行数数据
     * @param $row
     */
    public function take($row)
    {
        $this->limit(0, $row);
        return $this;
    }

    /**
     * @param $start
     * @param $size
     */
    public function limit($start, $row)
    {
        $this->limit = [
            $start,
            $row
        ];

        return $this;
    }

    /**
     * 结果转换为Model实例
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function resultToModel($result)
    {
        $modelInstance = App::getInstance()->make($this->getModel()->getClassName());
        $modelInstance->setData($result);
        return $modelInstance;
    }

    /**
     * 执行查询
     * @param $sql
     * @param $bindings
     * @param \Closure $callback
     * @return mixed
     */
    protected function run($sql, $bindings, \Closure $callback)
    {
        $result = $callback($sql, $bindings);

        // 重置
        // TODO 暂时先注释掉
        //$this->reset();
        return $result;
    }

    /**
     * 生成delete语句
     * @return string
     */
    protected function generateDeleteSQL()
    {
        $sql = 'DELETE FROM ' . $this->getTableName() . $this->generateConditionsSQL();
        return $sql;
    }

    /**
     * 生成update语句
     * @param array $data
     * @return string
     */
    protected function generateUpdateSQL(array $data)
    {
        $sql = 'UPDATE ' . $this->getTableName() . ' SET ';
        $setFields = [];
        foreach ($data as $field => $value) {
            $this->prepareBindings($value);
            $setFields[] = $field . ' = ' . $value;
        }

        $sql .= implode(', ', $setFields) . $this->generateConditionsSQL();
        return $sql;
    }

    /**
     * 生成limit语句
     * @return string
     */
    protected function generateLimitSQL()
    {
        $sql = '';
        if (!empty($this->limit)) {
            $this->prepareBindings($this->limit[0]);
            $this->prepareBindings($this->limit[1]);
            $sql = ' LIMIT ' . $this->limit[0] . ', ' . $this->limit[1];
        }
        return $sql;
    }

    /**
     * 生成groupBy语句
     * @return string
     */
    protected function generateGroupBySQL()
    {
        $sql = '';
        if (!empty($this->groupBy)) {
            array_walk($this->groupBy, function (&$field, $index) {
                $this->prepareBindings($field);
            });

            $sql = ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        return $sql;
    }

    /**
     * 生成orderBy语句
     * @return mixed|string
     */
    protected function generateOrderBySQL()
    {
        if (empty($this->orderBy)) {
            return '';
        }

        $initial = ' ORDER BY ';
        return array_reduce($this->orderBy, function ($carry, $item) use ($initial) {
            if ($carry != $initial) {
                $carry .= ', ';
            }

            $this->prepareBindings($item['field']);
            $this->prepareBindings($item['type']);

            return $carry . $item['field'] . ' ' . $item['type'];

        }, $initial);
    }

    protected function reset()
    {
        $this->resetConditions();
        $this->resetBindings();
        $this->resetLimit();
        $this->resetGroupBy();
        $this->resetOrderBy();
        $this->resetJoins();
        $this->resetRelationship();
    }

    protected function resetConditions()
    {
        $this->conditions = [];
    }

    protected function resetBindings()
    {
        $this->bindings = [];
    }

    protected function resetLimit()
    {
        $this->limit = [];
    }

    protected function resetGroupBy()
    {
        $this->groupBy = [];
    }

    protected function resetOrderBy()
    {
        $this->orderBy = [];
    }

    protected function resetJoins()
    {
        $this->joins = [];
    }

    protected function resetRelationship()
    {
        $this->relationships = [];
    }

    /**
     * 添加条件
     * @param $type
     * @param $pattern
     */
    protected function addCondition($type, $pattern)
    {
        if (empty($this->conditions)) {
            $type = '';
        }
        $this->conditions[] = compact('type', 'pattern');
    }

    /**
     * 生成表达式
     * @param $column
     * @param $operator
     * @param $value
     * @return array
     */
    protected function newPattern($column, $operator, $value)
    {
        return [
            $column,
            $operator,
            $value
        ];
    }

    /**
     * 生成SQL条件语句
     * @return string
     */
    protected function generateConditionsSQL($where = true)
    {
        if (empty($this->conditions)) {
            return '';
        }

        if ($where) {
            $sql = ' WHERE';
        } else {
            $sql = ' ON';
        }

        foreach ($this->conditions as $condition) {
            if (isset($condition['pattern'][2])) {
                // pattern方式条件 pattern[0]为字段名 2为操作符 3位绑定参数
                $this->prepareBindings($condition['pattern'][2]);

                $sql .= ' ' . $condition['type'] . ' ' . implode(' ', $condition['pattern']);
            } else {
                // raw方式条件 pattern[0]为原生sql表达式 1为绑定参数的数组
                foreach ($condition['pattern'][1] as $value) {
                    $this->prepareBindings($value);
                }

                $sql .= ' ' . $condition['type'] . ' ' . $condition['pattern'][0];
            }
        }

        return $sql;
    }

    /**
     * 生成查询语句
     * @param array $column
     * @return string
     */
    protected function generateSelectSQL($column = ['*'])
    {
        $sql = 'SELECT ' . implode(',', $column) . ' FROM ' . $this->getTableName()
            . $this->generateJoinsSQL()
            . $this->generateConditionsSQL()
            . $this->generateGroupBySQL()
            . $this->generateOrderBySQL()
            . $this->generateLimitSQL();

        return $sql;
    }

    protected function generateJoinsSQL()
    {
        $sql = '';

        foreach ($this->joins as $item) {
            $sql .= ' ' . $item['type'] . ' JOIN ' . $item['table'] . $item['builder']->generateConditionsSQL(false);
        }

        return $sql;
    }

    /**
     * 生成insert语句
     * @param array $data
     * @return string
     */
    protected function generateInsertSQL(array $data)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $this->prepareBindings($values);


        $sql = 'INSERT INTO ' . $this->getTableName() . ' (' . implode(',', $fields) . ') VALUES ' . $values;
        return $sql;
    }

    /**
     * 预处理参数绑定相关
     * @param $pattern
     */
    protected function prepareBindings(&$param)
    {
        if (is_array($param)) {
            foreach ($param as $index => $item) {
                $this->bindings[] = $item;
                $param[$index] = '?';
            }

            $param = '(' . implode(',', $param) . ')';
        } else {
            $this->bindings[] = $param;
            $param = '?';
        }
    }
}