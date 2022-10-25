<?php

namespace yii2custom\common\core;

class I18N extends \yii\i18n\I18N
{
    public function translate($category, $message, $params, $language)
    {
        return parent::translate($category, $message, $params, $language);
    }
}