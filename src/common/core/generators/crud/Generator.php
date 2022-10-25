<?php

namespace yii2custom\common\core\generators\crud;

class Generator extends \yii\gii\generators\crud\Generator
{
    const RESERVED_ATTRIBUTES = ['priority', 'image_id', ...self::ATBY_ATTRIBUTES];
    const ATBY_ATTRIBUTES = ['created_at', 'updated_at', 'created_by', 'updated_by'];

    public function generateActiveField($attribute)
    {
        $result = parent::generateActiveField($attribute);
        return str_replace(['[\'maxlength\' => true]', '[\'rows\' => 6]'], '', $result);
    }
}