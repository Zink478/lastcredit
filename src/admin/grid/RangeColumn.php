<?php

namespace yii2custom\admin\grid;

use yii2custom\admin\grid\DataColumn;
use yii2custom\admin\core\GridView;

class RangeColumn extends DataColumn
{
    public $headerOptions = ['style' => 'width: 15%'];

    public function init()
    {
        $this->filter = \kartik\field\FieldRange::widget([
            'model' => $this->grid->filterModel,
            'attribute1' => $this->attribute . '_from',
            'attribute2' => $this->attribute . '_to',
            'type' => \kartik\field\FieldRange::INPUT_TEXT,
        ]);

        parent::init();
    }
}