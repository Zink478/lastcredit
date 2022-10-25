<?php

namespace yii2custom\admin\grid;

use yii2custom\admin\core\GridView;

class DateColumn extends DataColumn
{
    public $format = 'datetime';
    public $headerOptions = ['style' => 'width: 15%'];

    public function init()
    {
        $this->filterType = GridView::FILTER_DATE_RANGE;
        $this->filterWidgetOptions['pluginOptions']['locale']['format'] = 'DD-MM-YYYY';

        parent::init();
    }
}