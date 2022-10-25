<?php

namespace yii2custom\admin\grid;

use kartik\select2\Select2;
use yii\base\InvalidConfigException;
use yii\helpers\Html;

class SlugColumn extends DataColumn
{
    public $path = null;

    public function init()
    {
        parent::init();

        if (!$this->path) {
            throw new InvalidConfigException('Property path must be defined.');
        }
    }

    protected function renderDataCellContent($model, $key, $index)
    {
        $slug = $model->{$this->attribute};
        return Html::a($slug, \Yii::getAlias('@host') . $this->path . '/' . $slug, ['target' => '_blank']);
    }
}