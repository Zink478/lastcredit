<?php

namespace yii2custom\admin\grid;

use kartik\grid\DataColumn;
use kartik\select2\Select2;
use yii\base\Exception;
use yii\helpers\Inflector;
use yii2custom\common\core\ActiveRecord;

class RelationColumn extends DataColumn
{
    /** @var array|null */
    public $data = null;
    public $relation = null;
    public $conditions = [];

    public $enableSorting = false;
    public $headerOptions = ['style' => 'width: 20%'];

    public function init()
    {
        parent::init();
        /** @var string|ActiveRecord $searchModel */
        /** @var string|ActiveRecord $relationClass */
        $searchModel = $this->grid->filterModel;
        $relationName = $this->relation();
        $relation = $searchModel->getRelation($relationName);
        $relationClass = $relation->modelClass;
        $this->value = $this->value ?? $relationName . '.' . $relationClass::titleAttribute();

        if ($this->filter === null) {
            $this->filter = Select2::widget([
                'model' => $this->grid->filterModel,
                'attribute' => $this->attribute,
                'data' => $this->data ?: $relationClass::{'list'}($relationClass::titleAttribute()),
                'options' => ['placeholder' => ''],
                'pluginOptions' => ['allowClear' => true],
            ]);
        }
    }

    protected function relation()
    {
        return $this->relation ?? lcfirst(Inflector::camelize(substr($this->attribute, 0, -strlen('_id'))));
    }
}