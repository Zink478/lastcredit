<?php

namespace yii2custom\common\behaviors;

use yii2custom\common\core\ActiveRecord;
use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeBehavior;

class DateTimeBehavior extends AttributeBehavior
{
    /** @var ActiveRecord */
    public $owner;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            throw new InvalidConfigException('"attributes" property is required');
        }
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'fromTimestamp',
            ActiveRecord::EVENT_AFTER_VALIDATE => 'toTimestamp',
        ];
    }

    public function fromTimestamp()
    {
        if ($this->owner::isSearchModel()) {
            return;
        }

        foreach ($this->attributes as $attribute) {
            $value = $this->owner->getAttribute($attribute);

            if (is_integer($value)) {
                $value = Yii::$app->formatter->asDatetime($value);
                $this->owner->setAttribute($attribute, $value);
            }
        }
    }

    public function toTimestamp()
    {
        if ($this->owner::isSearchModel()) {
            return;
        }

        foreach ($this->attributes as $attribute) {
            $value = $this->owner->getAttribute($attribute);
            if (!is_null($value) && !is_integer($value)) {
                if (preg_match('#([\d]{2})/([\d]{2})/([\d]{2}) ([\d]{2}):([\d]{2})#', $value, $ms)) {
                    $dt = new \DateTime();
                    $dt->setDate('20' . $ms[1], $ms[2], $ms[3]);
                    $dt->setTime($ms[4], $ms[5]);
                    $value = $dt->getTimestamp();
                }
            }
            $this->owner->setAttribute($attribute, $value);
        }
    }
}
