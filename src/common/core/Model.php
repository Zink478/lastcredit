<?php

namespace yii2custom\common\core;

use yii2custom\common\traits\ActiveRecord\TEnum;
use yii2custom\common\traits\ActiveRecord\TSchema;

class Model extends \yii\base\Model
{
    const SCENARIO_DEFAULT = 'default';
    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';
    const SCENARIO_VIEW = 'view';

    use TSchema;
    use TEnum;

    public function formName()
    {
        return '';
    }

    public static function isSearchModel()
    {
        return false;
    }

    public function hasAttribute($name)
    {
        return parent::hasProperty($name, true, true);
    }

    public function relations()
    {
        return [];
    }

    public function getRelation()
    {
        return null;
    }

    /**
     * Title Column
     * @return string
     */
    public static function titleAttribute()
    {
        return 'title';
    }

    /**
     * @inheritDoc
     *
     * Added manualy defined scenarious by class constants
     */
    public function scenarios()
    {
        $result = [];
        $empty = array_fill_keys(static::enum('scenario'), []);
        $scenarios = array_merge($empty, parent::scenarios());

        // Defaults for update scenario
        if (!isset($scenarios[self::SCENARIO_UPDATE])) {
            $scenarios[self::SCENARIO_UPDATE] = $scenarios[self::SCENARIO_DEFAULT] ?? [];
        }

        // Defaults for create scenario
        if (!isset($scenarios[self::SCENARIO_CREATE])) {
            $scenarios[self::SCENARIO_CREATE] = $scenarios[self::SCENARIO_UPDATE] ?? $scenarios[self::SCENARIO_DEFAULT] ?? [];
        }

        // Defaults for view scenario
        if (!isset($scenarios[self::SCENARIO_VIEW])) {
            $scenarios[self::SCENARIO_VIEW] = $scenarios[self::SCENARIO_UPDATE] ?? $scenarios[self::SCENARIO_DEFAULT] ?? [];
        }

        return $scenarios;
    }
}