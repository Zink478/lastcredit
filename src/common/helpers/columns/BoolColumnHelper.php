<?php


namespace yii2custom\common\helpers\columns;


use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii2custom\common\core\ActiveQuery;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\helpers\ModelHelper;

class BoolColumnHelper
{
    /**
     * @param ActiveQuery $query
     * @param string|array $relation
     * @param array $condition
     * @param bool $value
     */
    public static function where(ActiveQuery $query, $relation, array $condition = [], bool $value = true)
    {
        if (is_array($relation)) {
            $conditions = $relation;
            if (!empty($conditions[0]) && is_string($conditions[0])) {
                $conditions = [$conditions];
            }
        } else {
            $conditions = [[$relation, $condition, $value]];
        }

        foreach ($conditions as $attribute => $item) {
            $relation = $item[0];
            $condition = $item[1];
            $value = $item[2];

            static::_where($query, $relation, $condition, $value);
        }
    }

    public static function filter(ActiveQuery $query, $relation, array $condition = [], bool $value = true)
    {
        if (is_string($relation)) {
            $conditions = [[$relation, $condition, $value]];
        } elseif (ArrayHelper::isIndexed($relation)) {
            $conditions = is_array([$relation[0]]) ? $relation : [$relation];
        }

        foreach ($conditions as $index => $item) {
            if ($item[2] === null) {
                unset($conditions[$index]);
            }
        }

        static::where($query, $conditions);
    }

    public static function get(ActiveRecord $model, string $relation, array $condition)
    {
        $conditionAttribute = key($condition);
        $conditionValue = current($condition);

        foreach ($model->{$relation} as $list) {
            if ($list->{$conditionAttribute} == $conditionValue) {
                return true;
            }
        }
        return false;
    }

    public static function set(ActiveRecord $model, string $relation, array $condition, ?bool $value)
    {
        $conditionAttribute = key($condition);
        $conditionValue = current($condition);

        if ($value) {
            if (!$model->{$relation}) {
                $model->{$relation} = [[$conditionAttribute => $conditionValue]];
            } else {
                $exists = false;
                foreach ($model->{$relation} as $key => $list) {
                    if ($list->{$conditionAttribute} == $conditionValue) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $model->{$relation} = [...$model->{$relation}, [$conditionAttribute => $conditionValue]];
                }
            }
        } else if ($model->{$relation}) {
            $lists = $model->{$relation};
            foreach ($lists as $key => $list) {
                if ($list->{$conditionAttribute} == $conditionValue) {
                    unset($lists[$key]);
                }
            }
            $model->{$relation} = $lists;
        }
    }

    protected static function _where(ActiveQuery $query, string $relation, array $condition, bool $value = true)
    {
        /** @var ActiveRecord|string $modelClass */
        /** @var ActiveRecord|string $relationClass */
        /** @var ActiveQuery $relationQuery */

        $conditionAttribute = key($condition);
        $conditionColumn = Inflector::underscore($conditionAttribute);
        $conditionValue = current($condition);
        $relationQuery = (new $query->modelClass())->getRelation($relation);
        $relationClass = $relationQuery->modelClass;
        $table = $relationClass::tableName();
        $alias = ModelHelper::alias();

        $query->joinWith([$relation . ' ' . $alias => function (ActiveQuery $query)
        use ($alias, $conditionColumn, $conditionAttribute, $conditionValue) {
            $query->andOnCondition([$alias . '.' . $conditionColumn => $conditionValue]);
        }], false);

        $condition = $value ? ['not', [$alias => null]] : [$alias => null];
        $query->andOnCondition($condition);
    }
}