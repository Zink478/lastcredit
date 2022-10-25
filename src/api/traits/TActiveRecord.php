<?php

namespace yii2custom\api\traits;

/**
 * @deprecated
 */
trait TActiveRecord
{
    public function behaviors()
    {
        return array_diff_key(parent::behaviors(), array_flip(['blame']));
    }
}