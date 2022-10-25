<?php

namespace yii2custom\admin\grid;

use kartik\grid\DataColumn;

class IDColumn extends DataColumn
{
    public $attribute = 'id';
    public $headerOptions = ['style' => 'width: 1%'];
}