<?php

namespace More\Src\Lib\Database\Relation;


use More\Src\Lib\Database\Model;

class HasMany extends Relation
{
    /**
     * 获取结果集
     * @param array|Model $parent
     * @param array $column
     * @return array|Model
     */
    public function getResult($parent, array $column = ['*'], \Closure $helper = null)
    {
        $this->setParent($parent);
        $this->addConditions($helper);
        return $this->related->get($column);
    }

    public function getCount($parent, \Closure $helper = null)
    {
        $this->setParent($parent);
        $this->addConditions($helper);
        return $this->related->count('*');
    }
}