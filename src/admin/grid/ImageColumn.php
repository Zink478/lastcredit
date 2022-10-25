<?php

namespace yii2custom\admin\grid;

use kartik\grid\DataColumn;

class ImageColumn extends DataColumn
{
    public $size = 100;

    public $filter = false;
    public $enableSorting = false;
    public $headerOptions = ['style' => 'width: 1%'];

    public function init()
    {
        parent::init();
        $this->attribute = $this->attribute ?? 'image';
        $this->format = ['image', ['style' => ['max-width' => "{$this->size}px", 'max-height' => "{$this->size}px"]]];
    }

    public function getDataCellValue($model, $key, $index)
    {
        $value = parent::getDataCellValue($model, $key, $index);
        return $value ? \Yii::getAlias('@media-web') . $value : null;
    }
}