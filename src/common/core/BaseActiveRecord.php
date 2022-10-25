<?php

namespace yii2custom\common\core;

use yii2custom\common\traits\ActiveRecord\TActiveRecord;
use yii2custom\common\traits\ActiveRecord\TEnum;
use yii2custom\common\traits\ActiveRecord\TI18n;
use yii2custom\common\traits\ActiveRecord\TMust;
use yii2custom\common\traits\ActiveRecord\TSchema;

abstract class BaseActiveRecord extends \yii\db\ActiveRecord
{
    const SCENARIO_DEFAULT = 'default';
    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';
    const SCENARIO_VIEW = 'view';

    use TEnum;
    use TI18n;
    use TMust;
    use TSchema;
    use TActiveRecord;
}