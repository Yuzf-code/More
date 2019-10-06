<?php

namespace More\Src\Lib\Database;


use More\Src\Core\App;
use More\Src\Lib\Database\Relation\HasMany;
use More\Src\Lib\Database\Relation\Relation;

class Builder
{
    /**
     * 查询结果集返回类型
     */
    const RESULT_TYPE_ARRAY = 1;
    const RESULT_TYPE_MODEL = 2;

    /**
     * 软删除模式下的查询方式
     */
    const SOFT_DELETE_QUERY_TYPE_DEFAULT = 1;
    const SOFT_DELETE_QUERY_TYPE_WITH_DELETED = 2;
    const SOFT_DELETE_QUERY_TYPE_ONLY_DELETED = 3;

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
     * 别名
     * @var string
     */
    protected $alias = '';

    /**
     * 是否使用软删除
     * @var bool
     */
    protected $softDelete = false;

    protected $softDeleteQueryType = 1;

    /**
     * 软删除时间字段
     * @var string
     */
    protected $deleteDate = 'delete_date';

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
        if (empty($this->model)) {
            throw new \Exception('model can not be null.');
        }

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
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @param string $alias
     */
    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }



    /**
     * 获取表名
     * @return string
     */
    public function getTableName($withAlias = true)
    {
        if ($this->table == null) {
            throw new \Exception('Table Can not be null.');
        }

        $tableName = $this->db->tableName($this->table);

        if ($withAlias && !empty($this->alias)) {
            $tableName .= ' AS ' . $this->alias;
        }

        return $tableName;
    }

    /**
     * @param bool $softDelete
     */
    public function setSoftDelete(bool $softDelete): void
    {
        $this->softDelete = $softDelete;
    }

    /**
     * @param string $deleteDate
     */
    public function setDeleteDate(string $deleteDate): void
    {
        $this->deleteDate = $deleteDate;
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

    /**
     * 获取模型主键
     * @return string
     * @throws \Exception
     */
    protected function getPrimaryKey()
    {
        return $this->getModel()->getPrimaryKey();
    }

    protected function softDeleteFilter()
    {
        if ($this->softDeleteQueryType == self:: SOFT_DELETE_QUERY_TYPE_WITH_DELETED) {
            // 包含被软删除的数据，则不用额外添加筛选条件
            return;
        }

        $operate = $this->softDeleteQueryType == self::SOFT_DELETE_QUERY_TYPE_ONLY_DELETED ? ' IS NOT NULL' : ' IS NULL';
        $this->whereRaw($this->getTableName(true) . '.' . $this->deleteDate . $operate);
    }

    /**
     * 包含软删除数据
     * @return $this
     */
    public function withDeleted()
    {
        $this->softDeleteQueryType = self::SOFT_DELETE_QUERY_TYPE_WITH_DELETED;
        return $this;
    }

    /**
     * 仅查询软删除数据
     * @return $this
     */
    public function onlyDeleted()
    {
        $this->softDeleteQueryType = self::SOFT_DELETE_QUERY_TYPE_ONLY_DELETED;
        return $this;
    }

    /**
     * 获取多条
     * @param array $column
     * @return mixed
     */
    public function get($column = ['*'])
    {
        if ($this->softDelete) {
            $this->softDeleteFilter();
        }

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

        if ($this->softDelete) {
            $this->softDeleteFilter();
        }

        return $this->run($this->generateSelectSQL($column), $this->bindings, function ($sql, $bindings) {
            $data = $this->db->selectOne($sql, $bindings);

            // 没有数据直接返回null
            if (empty($data)) {
                return null;
            }

            // handle relationship
            $this->loadRelationship($data);

            // 为了可以方便的使用$model->save() 还是直接set一下
            if (!empty($this->model)) {
                $this->getModel()->setData($data);
            }

            if ($this->resultType == self::RESULT_TYPE_MODEL) {
                return $this->getModel();
            } else {
                return $data;
            }
        });
    }

    /**
     * 根据主键查询
     * @param $id
     * @param array $column
     * @return array|Model
     */
    public function find($id, $column = ['*'])
    {
        return $this->where($this->model->getPrimaryKey(), $id)->first($column);
    }

    /**
     * 插入数据
     * @param array $data
     * @return bool
     */
    public function insert(array $data)
    {
        return $this->run($this->generateInsertSQL($data), $this->bindings, function ($sql, $bindings) {
            return $this->db->insert($sql, $bindings);
        });
    }

    /**
     * 获取上一条插入的id
     * @return mixed
     */
    public function lastInsertId($name = null)
    {
        return $this->db->lastInsertId($name);
    }

    /**
     * 更新数据
     * @param array $data
     * @return int 返回受影响行数
     */
    public function update(array $data)
    {
        if ($this->softDelete) {
            $this->softDeleteFilter();
        }

        return $this->run($this->generateUpdateSQL($data), $this->bindings, function ($sql, $bindings) {
            return $this->db->update($sql, $bindings);
        });
    }

    /**
     * 使用原生语句更新
     * @param string $sql
     * @param array $bindings
     * @return mixed
     */
    public function updateRaw(string $sql, array $bindings = [])
    {
        if ($this->softDelete) {
            $this->softDeleteFilter();
        }

        return $this->run($this->generateUpdateRawSQL($sql, $bindings), $this->bindings, function ($sql, $bindings) {
            return $this->db->update($sql, $bindings);
        });
    }

    /**
     * 删除数据
     * @return int 返回受影响行数
     * @throws \Exception
     */
    public function delete()
    {
        if (!$this->hasConditions()) {
            throw new \Exception('Method delete() must has conditions');
        }


        // 有主键的话，自动添加主键条件
        if ($this->model->getKey() !== null) {
            $this->where($this->model->getPrimaryKey(), $this->model->getKey());
        }

        if ($this->softDelete) {
            return $this->update([
                $this->deleteDate => date('Y-m-d H:i:s')
            ]);
        }

        return $this->run($this->generateDeleteSQL(), $this->bindings, function ($sql, $bindings) {
            return $this->db->delete($sql, $bindings);
        });
    }

    /**
     * 根据主键删除
     * @param $ids
     * @return int
     * @throws \Exception
     */
    public function destroy($ids)
    {
        $ids = is_array($ids) ? $ids : func_get_args();

        return $this->where($this->model->getPrimaryKey(), 'IN', $ids)->delete();
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
    public function count($field)
    {
        if ($this->softDelete) {
            $this->softDeleteFilter();
        }

        $field = 'COUNT(' . $field . ')';

        $result = $this->run($this->generateSelectSQL([$field]), $this->bindings, function ($sql, $bindings) {
            return $this->db->selectOne($sql, $bindings);
        });

        return $result[$field];
    }

    /**
     * join操作的on条件
     * @param $first
     * @param $operator
     * @param $second
     * @return Builder
     */
    public function on($first, $operator, $second)
    {
        return $this->whereRaw($first . $operator . $second);
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
            $builder->on($first, $operator, $second);
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
        return $this->join($table, $first, $operator, $second , 'LEFT');
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
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * 注册关联模型方法
     * @param $relationship
     * @param array $column
     * @param \Closure|null $helper
     * @return $this
     * @throws \Exception
     */
    public function with($relationship, array $column = ['*'], \Closure $helper = null)
    {
        $model = $this->getModel();

        if (!method_exists($model, $relationship)) {
            throw new \Exception("relationship method not found.");
        }

        $relation = $model->$relationship();

        if (!$relation instanceof Relation) {
            throw new \Exception("relationship method must return an Relation instance.");
        }

        $this->addRelationship($relationship, $relation, $column, $helper);

        return $this;
    }

    public function withCount($relationship, $fieldName = '', \Closure $helper = null)
    {
        $model = $this->getModel();

        if (!method_exists($model, $relationship)) {
            throw new \Exception("relationship method not found.");
        }

        $relation = $model->$relationship();

        if (!$relation instanceof HasMany) {
            throw new \Exception("relationship method must return an HasMany instance.");
        }

        if ($fieldName == '') {
            $fieldName = $relationship;
        }

        $this->addRelationship($fieldName, $relation, null, $helper, true);
    }

    /**
     * 添加关联关系
     * @param $relationship
     * @param Relation $relation
     * @param array $column
     */
    public function addRelationship($relationship, Relation $relation, array $column = ['*'], \Closure $helper = null, $isCount = false)
    {
        $this->relationships[$relationship] = compact('relation', 'column', 'helper', 'isCount');
    }

    /**
     * 挂载关联模型数据
     * @param array | Model $row
     */
    protected function loadRelationship(&$row)
    {
        foreach ($this->relationships as $key => $item) {
            if ($item['isCount']) {
                // withCount
                $row[$key] = $item['relation']->getCount($row, $item['helper']);
            } else {
                $row[$key] = $item['relation']->getResult($row, $item['column'], $item['helper']);
            }
        }
    }

    /**
     * 是否已经添加过查询条件
     * @return bool
     */
    public function hasConditions()
    {
        return !empty($this->conditions);
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
        $this->reset();
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

    protected function generateUpdateRawSQL($sql, $bindings)
    {
        foreach ($bindings as $value) {
            $this->prepareBindings($value);
        }

        $sql = 'UPDATE ' . $this->getTableName() . ' SET ' . $sql . $this->generateConditionsSQL();

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

    public function reset()
    {
        $this->resetConditions();
        $this->resetBindings();
        $this->resetLimit();
        $this->resetGroupBy();
        $this->resetOrderBy();
        $this->resetJoins();
        $this->resetRelationship();
    }

    public function resetConditions()
    {
        $this->conditions = [];
    }

    public function resetBindings()
    {
        $this->bindings = [];
    }

    public function resetLimit()
    {
        $this->limit = [];
    }

    public function resetGroupBy()
    {
        $this->groupBy = [];
    }

    public function resetOrderBy()
    {
        $this->orderBy = [];
    }

    public function resetJoins()
    {
        $this->joins = [];
    }

    public function resetRelationship()
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
            $sql .= ' ' . $item['type'] . ' JOIN ' . $this->db->tableName($item['table']) . $item['builder']->generateConditionsSQL(false);
            $this->addBindings($item['builder']->getBindings());
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


        $sql = 'INSERT INTO ' . $this->getTableName(false) . ' (' . implode(',', $fields) . ') VALUES ' . $values;
        return $sql;
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    public function addBindings($value)
    {
        if (is_array($value)) {
            $this->bindings = array_merge($this->bindings, $value);
        } else {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * 预处理参数绑定相关
     * @param $pattern
     */
    protected function prepareBindings(&$param)
    {
        if (is_array($param)) {
            foreach ($param as $index => $item) {
                $this->addBindings($item);
                $param[$index] = '?';
            }

            $param = '(' . implode(',', $param) . ')';
        } else {
            $this->addBindings($param);
            $param = '?';
        }
    }
}