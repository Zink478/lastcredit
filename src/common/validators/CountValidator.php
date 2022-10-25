<?php

namespace yii2custom\common\validators;

use yii\base\InvalidConfigException;

class CountValidator extends \yii\validators\Validator
{
    public $count;

    public function init()
    {
        if (!$this->count) {
            throw new InvalidConfigException('Property count must be set');
        }

        if ($this->message === null) {
            $this->message = \Yii::t('yii', 'Maximum count of {attribute} cannot exceed more than {count}.');
        }

        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        if (count($value) > $this->count) {
            return [$this->message, [
                'count' => $this->count
            ]];
        }
        return null;
    }
}