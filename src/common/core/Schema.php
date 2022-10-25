<?php

namespace yii2custom\common\core;
use Yii;

class Schema extends \tigrov\pgsql\Schema
{
    /**
     * @inheritDoc
     */
    protected function loadColumnSchema($info)
    {
        $info['type_scheme'] = $info['type_scheme'] ?? null;
        return parent::loadColumnSchema($info);
    }

    /**
     * @return ColumnSchema
     * @inheritDoc
     */
    protected function createColumnSchema()
    {
        return Yii::createObject(ColumnSchema::className());
    }
}