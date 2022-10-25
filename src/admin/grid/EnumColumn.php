<?php

namespace yii2custom\admin\grid;

use kartik\select2\Select2;
use yii2custom\common\core\ActiveRecord;
use yii\helpers\Inflector;

class EnumColumn extends DataColumn
{
    /** @var array|null */
    public $data = null;
    public $headerOptions = ['style' => 'width: 10%'];

    public function init()
    {
        /** @var ActiveRecord $filterModel */
        $filterModel = $this->grid->filterModel;
        $this->filter = $this->data;
        if (!$this->filter && $filterModel) {
            $this->filter = $filterModel::enum($this->attribute);
        }

        if ($filterModel) {
            $this->filter = Select2::widget([
                'model' => $this->grid->filterModel,
                'attribute' => $this->attribute,
                'data' => $this->filter,
                'options' => array_merge(['value' => $filterModel->{$this->attribute}], $this->filterInputOptions),
                'pluginOptions' => ['allowClear' => false, 'minimumResultsForSearch' => 'Infinity', 'placeholder' => 'All', 'allowClear' => true],
            ]);
        }

        parent::init();
    }

    public function getDataCellValue($model, $key, $index)
    {
        /** @var ActiveRecord $model */
        $value = parent::getDataCellValue($model, $key, $index);
        return $model->title($this->attribute, $value);
    }
}