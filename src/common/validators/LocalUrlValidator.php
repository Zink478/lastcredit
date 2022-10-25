<?php

namespace yii2custom\common\validators;

class LocalUrlValidator extends \yii\validators\UrlValidator
{
    public $pattern = '/^[a-z0-9-\\/\\#]+$/';

    public function init()
    {
        if ($this->message === null) {
            $this->message = \Yii::t('yii', '{attribute} is not a valid local URL.');
        }

        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $model->$attribute = '/' . ltrim($model->$attribute, '/');
        parent::validateAttribute($model, $attribute);
    }
}