<?php

namespace yii2custom\admin\core;

use yii2custom\common\core\ActiveRecord;
use himiklab\sortablegrid\SortableGridAsset;
use yii\helpers\Url;

class GridView extends \kartik\grid\GridView
{
    public $enableSorting = null;
    public $dataColumnClass = 'yii2custom\admin\grid\DataColumn';

    /** @var string|array Sort action */
    public $sortableAction = ['sort'];

    /** @var ActiveRecord */
    public $filterModel;

    public function init()
    {
        if (is_null($this->enableSorting) && $this->filterModel && $this->filterModel->hasAttribute('priority')) {
            $this->enableSorting = true;
        }

        foreach ($this->columns as $key => $column) {
            if (is_null($column)) {
                unset($this->columns[$key]);
            }
        }

        parent::init();

        $this->sortableAction = Url::to($this->sortableAction);
    }

    public function run()
    {
        if ($this->enableSorting) {
            $this->registerWidget();
        }

        parent::run();
    }

    protected function registerWidget()
    {
        $view = $this->getView();
        $view->registerJs("jQuery('#{$this->options['id']}').SortableGridView('{$this->sortableAction}');");
        SortableGridAsset::register($view);
    }
}