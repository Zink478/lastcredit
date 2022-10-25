<?php

namespace yii2custom\common\core;

use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii2custom\common\behaviors\LogBehavior;
use yii2custom\common\behaviors\SaveBehavior;
use yii2custom\common\behaviors\SysLogBehavior;
use Yii;

/**
 * @property array $extra
 */
abstract class ActiveRecord extends BaseActiveRecord
{
    /** @var self|null */
    public $owner;

    protected $_extra = [];

    public function getExtra(): array
    {
        return $this->_extra;
    }

    public function setExtra(array $value)
    {
        $this->_extra = $value;
    }

    /**
     * @inheritDoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * @inheritDoc
     *
     * Filter fields by current scenario
     */
    public function fields()
    {
        $fields = $this->scenarios()[$this->getScenario()] ?? parent::fields();
        return array_combine($fields, $fields);
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

        if (isset($scenarios[self::SCENARIO_DEFAULT])) {
            $scenarios[self::SCENARIO_DEFAULT] = array_merge(
                $this->getTableSchema()->primaryKey,
                $scenarios[self::SCENARIO_DEFAULT]
            );
        }

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

    /**
     * @inheritDoc
     */
    public function load($data, $formName = null)
    {
        $formName = $formName ?? $this->formName();

        if (static::isSearchModel()) {
            foreach (($formName ? $data[$formName] ?? [] : $data) as $key => $value) {
                if ($value === '') {
                    if ($formName) {
                        unset($data[$formName][$key]);
                    } else {
                        unset($data[$key]);
                    }
                }
            }
        }

        $result = parent::load($data, $formName);

        if (isset($data['extra'])) {
            $this->extra = $data['extra'];
        }

        /** @var SaveBehavior $behavior */
        if ($result && ($behavior = $this->getBehavior('save')) !== null) {
            $result = $behavior->load($data, $formName);
        }

        return $result;
    }

    public function updateAttributes($attributes)
    {
        /** @var LogBehavior $behavior */
        $logBehavior = $this->getBehavior('log');
        if ($logBehavior instanceof SysLogBehavior) {
            $logBehavior->updateAttributes($attributes);
        }

        $tsBehavior = $this->getBehavior('timestamp');
        if ($tsBehavior instanceof TimestampBehavior) {
            if ($this->hasAttribute($tsBehavior->updatedAtAttribute)) {
                $attributes[$tsBehavior->updatedAtAttribute] = time();
            }
        }

        if (Yii::$app->hasProperty('user')) {
            $blameBehavior = $this->getBehavior('blame');
            if ($blameBehavior instanceof BlameableBehavior) {
                if ($this->hasAttribute($blameBehavior->updatedByAttribute)) {
                    $attributes[$tsBehavior->updatedAtAttribute] = Yii::$app->user->id;
                }
            }
        }

        if (!$this->beforeSave(false)) {
            return false;
        }

        $result = parent::updateAttributes($attributes);
        $this->afterSave(false, $attributes);

        return $result;
    }

    /**
     * Filter attributes by current scenario
     *
     * @inheritDoc
     */
    protected function updateInternal($attributes = null)
    {
        $attributes = $attributes ?? $this->attributes();
        return parent::updateInternal($attributes);
    }

    /**
     * Filter attributes by current scenario
     *
     * @inheritDoc
     */
    protected function insertInternal($attributes = null)
    {
        $attributes = $attributes ?? $this->attributes();
        return parent::insertInternal($attributes);
    }

    ///
    /// Helpers
    ///

    /**
     * Resolve and return field value
     *
     * @param $field
     * @param boolean $extra
     * @return mixed
     */
    public function field($field, $extra = false)
    {
        $fields = $extra ? [] : [$field];
        $extraFields = $extra ? [$field] : [];
        $result = $this->resolveFields($fields, $extraFields)[$field];
        return is_callable($result) ? $result() : $result;
    }

    protected function resolveFields(array $fields, array $expand)
    {
        $fields = $this->extractRootFields($fields);
        $expand = $this->extractRootFields($expand);
        $result = [];

        foreach (array_merge($this->fields(), $this->readonly()) as $field => $definition) {
            if (is_int($field)) {
                $field = $definition;
            }
            if (empty($fields) || in_array($field, $fields, true)) {
                $result[$field] = $definition;
            }
        }

        if (empty($expand)) {
            return $result;
        }

        foreach ($this->extraFields() as $field => $definition) {
            if (is_int($field)) {
                $field = $definition;
            }
            if (in_array($field, $expand, true)) {
                $result[$field] = $definition;
            }
        }

        return $result;
    }
}