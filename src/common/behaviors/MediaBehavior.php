<?php

namespace yii2custom\common\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\helpers\Inflector;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\helpers\ModelHelper;

/**
 * @property-read ActiveRecord $owner
 */
class MediaBehavior extends Behavior
{
    public $attribute = 'data';
    public $namedAttribute = 'client_id';

    private $_delete = false;
    private $_data;

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'onAfterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'onAfterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'onAfterDelete',
        ];
    }

    public function onAfterDelete(Event $event)
    {
        Yii::$app->media->delete(Inflector::camel2id(cl($this->owner)), $this->owner->id, $this->attribute, $this->getClientId());
    }

    public function onAfterInsert(Event $event)
    {
        if ($this->_data) {
            Yii::$app->media->create(Inflector::camel2id(cl($this->owner)), $this->owner->id, $this->attribute, $this->getClientId(), $this->_data);
        }
    }

    public function onAfterUpdate(Event $event)
    {
        $old = $this->owner->oldAttributes[$this->attribute] ?? null;

        if ($this->_data && $old !== $this->_data) {
            Yii::$app->media->update(Inflector::camel2id(cl($this->owner)), $this->owner->id, $this->attribute, $this->getClientId(), $this->_data);
        } else if ($this->_delete) {
            Yii::$app->media->delete(Inflector::camel2id(cl($this->owner)), $this->owner->id, $this->attribute, $this->getClientId());
        }
    }

    public function canSetProperty($name, $checkVars = true)
    {
        return parent::canSetProperty($name, $checkVars) ?: $name == $this->attribute;
    }

    public function canGetProperty($name, $checkVars = true)
    {
        return parent::canGetProperty($name, $checkVars) ?: $name == $this->attribute;
    }

    public function __get($name)
    {
        if ($this->canGetProperty($name)) {
            if (!$this->_data && !$this->owner->isNewRecord) {
                $this->_data = Yii::$app->media->file(Inflector::camel2id(cl($this->owner)), $this->owner->id, $this->attribute, $this->getClientId());
                return $this->_data;
            } else {
                return null;
            }
        }

        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        if ($this->canSetProperty($name)) {
            $this->_data = $value;
            if (!$value) {
                $this->_delete = true;
            }
        } else {
            parent::__set($name, $value);
        }
    }

    public function revert()
    {
        Yii::$app->media->revert(
            Inflector::camel2id(cl($this->owner)),
            $this->owner->id,
            $this->attribute,
            $this->owner->client_id
        );
    }

    private function getClientId()
    {
        return ModelHelper::value($this->owner, $this->namedAttribute);
    }
}