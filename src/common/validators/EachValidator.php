<?php

namespace yii2custom\common\validators;

class EachValidator extends \yii\validators\EachValidator
{
    public $allowScalar = true;

    public function validateAttribute($model, $attribute)
    {
        $value = $model->{$attribute};
        if ($this->allowScalar && !is_array($value)) {
            $model->{$attribute} = $value ? explode(',', $value) : [];
        }

        parent::validateAttribute($model, $attribute);

        if ($this->allowScalar && count($model->{$attribute}) == 1) {
            $model->{$attribute} = $value;
        }
    }
}