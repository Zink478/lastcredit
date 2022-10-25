<?php

namespace yii2custom\admin\grid;

use kartik\select2\Select2;

class BooleanColumn extends \kartik\grid\BooleanColumn
{
    public $allLabel = 'All';
    public $trueLabel = 'Yes';
    public $falseLabel = 'No';
    public $enableSorting = false;

    public function init()
    {
        parent::init();

        $value = $this->grid->filterModel->{$this->attribute};
        $this->filter = [
            '' => $this->allLabel,
            '1' => $this->trueLabel,
            '0' => $this->falseLabel,
        ];

        $this->filter = Select2::widget([
            'model' => $this->grid->filterModel,
            'attribute' => $this->attribute,
            'data' => $this->filter,
            'options' => array_merge(['value' => $value], $this->filterInputOptions),
            'pluginOptions' => ['allowClear' => false, 'minimumResultsForSearch' => 'Infinity'],
        ]);
    }
}