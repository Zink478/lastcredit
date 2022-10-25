<?php

namespace yii2custom\admin\grid;

use kartik\select2\Select2;
use yii\base\Model;
use yii\helpers\Html;
use yii2custom\common\core\ActiveRecord;

class DataColumn extends \kartik\grid\DataColumn
{
    protected function renderFilterCellContent()
    {
        if (is_string($this->filter)) {
            return $this->filter;
        }

        /** @var ActiveRecord $model */
        $model = $this->grid->filterModel;

        if ($this->filter !== false && $model instanceof Model && $this->attribute !== null && $model->isAttributeActive($this->attribute)) {
            if (is_array($this->filter)) {
                if ($model->hasErrors($this->attribute)) {
                    Html::addCssClass($this->filterOptions, 'has-error');
                    $error = ' ' . Html::error($model, $this->attribute, $this->grid->filterErrorOptions);
                } else {
                    $error = '';
                }

                $widget = Select2::widget([
                    'value' => $model->title($this->attribute, $model->{$this->attribute}),
                    'model' => $this->grid->filterModel,
                    'attribute' => $this->attribute,
                    'data' => $this->filter,
                    'options' => array_merge(['prompt' => '', 'placeholder' => ''], $this->filterInputOptions),
                    'pluginOptions' => ['allowClear' => true],
                ]);

                return $widget . $error;
            }
        }

        return parent::renderFilterCellContent();
    }
}