<?php

namespace yii2custom\common\core;

use Yii;
use yii2custom\common\helpers\columns\BoolColumnHelper;
use yii2custom\common\helpers\ModelHelper;

class ActiveQuery extends \yii\db\ActiveQuery
{
    public $defaultOrder = [];

    public function scalar($db = null)
    {
        // $this->prepareOrder(); TODO
        $this->prepareSelect();
        $this->limit(1);
        return parent::scalar($db);
    }

    public function column($db = null)
    {
        $this->prepareOrder();
        $this->prepareSelect();
        return parent::column($db);
    }

    public function one($db = null)
    {
        $this->prepareOrder();
        $this->prepareSelect();
        $this->limit(1);
        return parent::one($db);
    }

    public function all($db = null)
    {
        $this->prepareOrder();
        $this->prepareSelect();
        return parent::all($db);
    }

    public function findWith($with, &$models)
    {
        /** @var \yii2custom\common\core\ActiveRecord $model */
        $model = new $this->modelClass();
        $extra = $model->extraFields();
        $callable = [];

        foreach ($model->extraFields() as $key => $value) {
            if (is_callable($value)) {
                $callable[] = $key;
            }
        }

        foreach ($with as $key => $value) {
            $value = $value ?? $key;
            if (in_array(explode('.', $value)[0], $callable)) {
                unset($with[$key]);
            }
        }

        parent::findWith($with, $models);
    }

    public function page(int $page, int $size)
    {
        $this->limit($size)->offset(($page - 1) * $size);
        return $this;
    }

    // -- Filters --

    // Bool

    /**
     * @param string|array $relation
     * @param array|null $condition
     * @param bool|null $value
     */
    public function andBoolWhere($relation, array $condition = [], bool $value = true)
    {
        BoolColumnHelper::where($this, $relation, $condition, $value);
        return $this;
    }

    public function andBoolFilterWhere($relation, array $condition = [], bool $value = true)
    {
        BoolColumnHelper::filter($this, $relation, $condition, $value);
        return $this;
    }

    // Has

    public function andHasWhere(array $conditions)
    {
        foreach ($conditions as $attribute => $value) {
            if ($value) {
                $this->andWhere(['not', [$attribute => null]]);
            } else {
                $this->andWhere([$attribute => null]);
            }
        }

        return $this;
    }

    public function andHasFilterWhere(array $conditions)
    {
        foreach ($conditions as $attribute => $value) {
            if ($value === null) {
                unset($conditions[$attribute]);
            }
        }

        return $this->andHasWhere($conditions);
    }

    // Range

    public function andRangeFilterWhere(array $condition)
    {
        foreach ($condition as $attribute => $values) {
            if (!$values) {
                unset($condition[$attribute]);
            }
        }

        $this->andRangeWhere($condition);
    }

    public function andRangeWhere(array $condition)
    {
        foreach ($condition as $attribute => $values) {
            [$from, $to] = $values;

            if ($from) {
                $this->andWhere(['>=', $attribute, intval($from)]);
            }

            if ($to) {
                $this->andWhere(['<=', $attribute, intval($to)]);
            }
        }
    }

    // DateRange

    /**
     * @deprecated
     */
    public function andDateRangeFilterWhere(array $condition, $dateOnlyFromat = false)
    {
        return $this->andDateRangeWhere($condition, $dateOnlyFromat);
    }

    /**
     * @deprecated
     */
    public function andDateRangeWhere(array $condition, $dateOnlyFromat = false)
    {
        return $this->andDateFilterWhere($condition, $dateOnlyFromat);
    }

    public function andDateFilterWhere(array $condition, $dateOnlyFromat = false)
    {
        foreach ($condition as $attribute => $value) {
            if (!$value) {
                unset($condition[$attribute]);
            }
        }

        return $this->andDateWhere($condition, $dateOnlyFromat);
    }

    public function andInArrayFilterWhere($condition)
    {
        foreach ($condition as $attribute => $value) {
            if (!$value) {
                unset($condition[$attribute]);
            }
        }

        return $this->andInArrayWhere($condition);
    }

    public function andInArrayWhere($condition)
    {
        $tableName = $this->getPrimaryTableName();

        foreach ($condition as $attribute => $value) {
            if (str_contains($attribute, '.')) {
                [$tableName, $attribute] = explode('.', $attribute);
            }

            $tableSchema = Yii::$app->db->schema->getTableSchema($tableName);
            $columnSchema = $tableSchema->getColumn($attribute);
            $columnType = $columnSchema->dbType . (str_repeat('[]', $columnSchema->dimension));
            $dbValue = join(',', array_map(function ($v) {
                return "'$v'";
            }, explode(',', $value)));
            $this->andWhere("array[$dbValue]::$columnType && $attribute");
        }

        return $this;
    }

    public function andDateWhere(array $condition, $dateOnlyFromat = false)
    {
        foreach ($condition as $attribute => $value) {
            if (strlen($value) == 21) {
                $from = substr($value, 0, 10);
                $to = substr($value, 11);
            } else if (strpos($value, ' - ') !== false) {
                [$from, $to] = explode(' - ', $value);
            } else {
                $from = $to = $value;
            }

            $from = Yii::$app->formatter->asTimestamp($from . ' 00:00');
            $to = Yii::$app->formatter->asTimestamp($to . ' 23:59');

            if ($dateOnlyFromat) {
                $from = date('Y-m-d', $from);
                $to = date('Y-m-d', $to);
            }

            if ($from) {
                $this->andWhere(['>=', $attribute, $from]);
            }

            if ($to) {
                $this->andWhere(['<=', $attribute, $to]);
            }
        }

        return $this;
    }

    ///
    /// Protected
    ///

    protected function filterCondition($condition)
    {
        $condition = parent::filterCondition($condition);
        foreach ($condition as $k => &$v) {
            if ($v === '') {
                $v = null;
            }
        }

        return $condition;
    }

    protected function isEmpty($value)
    {
        return ($value !== '') && parent::isEmpty($value);
    }

    ///
    /// Private
    ///

    private function prepareOrder()
    {
        $this->orderBy = $this->orderBy ?? $this->defaultOrder;
    }

    private function prepareSelect()
    {
        if (!$this->select && $this->from) {
            $class = $this->modelClass;
            $key = array_key_first($this->from);
            $value = $this->from[$key];
            $from = is_string($key) ? $key : $value;
            /** @var ActiveRecord $model */
            $model = (new $class);

            $this->select(ModelHelper::columns($from, $model->readable()));
        }
    }
}