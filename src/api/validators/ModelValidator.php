<?php

namespace yii2custom\api\validators;

use yii2custom\common\core\Model;
use yii\validators\Validator;

class ModelValidator extends Validator
{
    public ?string $modelClass = null;

    public function validateAttribute($model, $attribute)
    {
        /** @var Model $value */
        $value = $model->$attribute;
        if (!$value->validate()) {
            $model->addError($attribute, $value->errors);
        }
    }
}