<?php

namespace yii2custom\common\behaviors;

use yii\base\Behavior;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
use yii\db\BaseActiveRecord;
use yii\db\Schema;
use yii\helpers\Inflector;
use yii\validators\RequiredValidator;

/**
 * Class SaveBehavior
 * @property ActiveRecord $owner
 */
class SaveBehavior extends Behavior
{
    /** @var string[] */
    public $relations = [];

    /** @var array[] */
    public $scenarios = [];

    /** @var bool|bool[] */
    public $default = true;

    protected $via = [];
    protected $diff = [
        'validators' => [],
        'relations' => []
    ];

    public function init()
    {
        parent::init();

        // Normalize settings

        $true = ['create' => true, 'update' => true, 'delete' => true];
        $false = ['create' => false, 'update' => false, 'delete' => false];
        $default = is_array($this->default) ? array_merge($true, $this->default) : ($this->default ? $true : $false);
        $relations = [];

        foreach ($this->relations as $key => $value) {
            $name = !is_numeric($key) ? $key : $value;
            $value = is_array($value) ? $value : [];
            $arr = explode('.', $name);
            if (count($arr) > 1) {
                $this->via[$arr[1]] = $arr[0];
                $relations[$arr[0]] = $default;
                $relations[$arr[1]] = array_merge($default, $value);
            } else {
                $relations[$arr[0]] = array_merge($default, $value);
            }
        }

        $this->relations = $relations;
        foreach ($this->scenarios as $scenario => &$relations) {
            foreach ($relations as &$value) {
                if (is_bool($value)) {
                    $value = $value ? $true : $false;
                } else {
                    $value = array_merge($default, $value);
                }
            }
        }
    }

    ///
    /// Public methods
    ///

    public function load($data, $formName = null)
    {
        $scope = $formName === null ? $this->owner->formName() : $formName;
        if ($scope === '' && !empty($data)) {
            $this->set($data);
        } elseif (isset($data[$scope])) {
            $this->set($data[$scope]);
        } else {
            return false;
        }

        return true;
    }

    public function names(?bool $depend = null, $populated = true)
    {
        $result = [];

        foreach (array_keys($this->relations) as $name) {
            $depends = $this->depends($name);
            if (is_null($depend)
                || ($depend && $depends)
                || (!$depend && !$depends)) {
                if (!$populated || $this->owner->isRelationPopulated($name)) {
                    $result[] = $name;
                }
            }
        }

        return $result;
    }

    ///
    /// Magic
    ///

    public function hasMethod($name)
    {
        return false;
    }

    public function canGetProperty($name, $checkVars = true)
    {
        return false;
    }

    public function canSetProperty($name, $checkVars = true)
    {
        return $checkVars && $this->hasProperty($name);
    }

    public function hasProperty($name, $checkVars = true)
    {
        return in_array($name, $this->names(null, false));
    }

    public function __set($name, $value)
    {
        // Diff

        $diff = null;
        if (!array_key_exists($name, $this->diff['relations'])) {
            if ($this->relation($name)->multiple) {
                $key = $this->via($name) ?? $name;
                $diff = $this->column($key)->column();
            } else if ($this->pk($name) != [$this->lk($name)]) {
                $diff = $this->column($name)->scalar() ?: null;
            } else {
                $diff = $this->owner->{$this->via($name) ? $this->lk($name) : $this->rk($name)};
            }

            $this->diff['relations'][$name] = $diff;
        }

        // Populate

        $relation = $this->relation($name);

        if (!$relation->multiple) {
            $model = $this->model($relation, $value);
            if ($model && ($model->isNewRecord || $model->primaryKey == $diff)) {
                if ($this->owner->{$name}) {
                    foreach ($value as $k => $v) {
                        $this->owner->{$name}[$k] = $v;
                    }
                } else {
                    $this->owner->{$name} = $model;
                    $this->owner->populateRelation($name, $model);
                }
            }
        } else {
            $pks = [];
            $models = $this->owner->{$name};

            foreach ($value as $item) {

                $new = true;
                $model = $this->model($relation, $item);
                $relations = $model->relations();
                $pks[] = $model->primaryKey;


                /** @var ActiveRecord $old */
                foreach ($this->owner->{$name} as $old) {
                    if ($model->primaryKey && ($model->primaryKey == $old->primaryKey)) {
                        $old->setAttributes($model->dirtyAttributes);

                        foreach ($relations as $relationName) {
                            $old->{$relationName} = $model->isRelationPopulated($relationName)
                                ? $model->{$relationName}
                                : ($old->getRelation($relationName)->multiple ? [] : null);
                        }

                        $new = false;
                        break;
                    }
                }

                if ($new) {
                    $models[] = $model;
                }
            }


            foreach ($models as $key => $old) {
                if (!in_array($old->primaryKey, $pks)) {
                    unset($models[$key]);
                }
            }

            $this->owner->populateRelation($name, $models);
        }
    }

    ///
    /// Events
    ///

    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete'
        ];
    }

    public function beforeValidate(ModelEvent $event)
    {
        $this->disableOwnerValidators();
        $this->disableRelatedValidators();
    }

    public function afterValidate(Event $event)
    {
        foreach ($this->names() as $name) {
            foreach ($this->records($name) as $record) {
                if ($record && !$record->validate()) {
                    $this->errors($record, $name);
                }
            }
        }

        $this->restoreOwnerValidators();
        $this->restoreRelatedValidators();
    }

    public function beforeSave(ModelEvent $event)
    {
        // Depended

        foreach ($this->names(true) as $name) {
            if ($this->owner->isRelationPopulated($name)) {
                if (($record = $this->record($name)) !== null) {
                    $action = $record->getIsNewRecord() ? 'create' : 'update';
                    $this->allow($name, $action);

                    if (!$record->save()) {
                        $this->errors($record, $name);
                        $event->isValid = false;
                    }
                    $this->owner->{$this->rk($name)} = $record->{$this->lk($name)};
                } else {
                    $this->owner->{$this->rk($name)} = null;
                }
            }
        }
    }

    public function afterSave(AfterSaveEvent $event)
    {
        // Remove

        $res = true;

        foreach ($this->names() as $name) {
            if (array_key_exists($name, $this->diff['relations'])) {
                $this->allow($name, 'delete');
                $oldPk = $this->diff['relations'][$name];
                if ($this->relation($name)->multiple) {
                    $newPk = array_column($this->records($name), 'primaryKey');
                    if (($diff = array_diff($oldPk, array_filter($newPk))) !== []) {
                        foreach ($this->find($name, $diff)->all() as $old) {
                            $old->delete();
                        }
                    }
                } else {
                    $record = $this->record($name);
                    $newPk = $record ? $record->primaryKey : null;
                    if (!is_null($oldPk) && $oldPk != $newPk) {
                        if (!$this->depends($name) || $this->dependsReverse($name)) {
                            $old = $this->find($name, $oldPk)->one();
                            $old && $old->delete();
                        }
                    }
                }
            }
        }

        // Not depended

        foreach ($this->names(false) as $name) {

            $records = [];
            $via = $this->via($name);

            foreach ($this->records($name) as $index => $record) {
                if (!$via) {
                    $record->{$this->lk($name)} = $this->owner->{$this->rk($name)};
                }

                $new = $record->getIsNewRecord();
                $this->allow($name, $new ? 'create' : 'update');

                if (!$record->save()) {
                    $this->errors($record, $name, $index);
                    $res = false;
                }

                if ($via && $new) {
                    $viaRelation = $this->relation($name)->via;
                    if (is_array($viaRelation)) {
                        $records[] = [
                            $this->rk($name) => $record->{$this->lk($name)},
                            $this->lk($via) => $this->owner->{$this->lk($name)}
                        ];
                    } else if ($via == $name) {
                        $class = ns($this->owner) . '\\' . Inflector::camelize(current($viaRelation->from));
                        $model = new $class();
                        $model->setAttributes([
                            $this->rk($name) => $record->{$this->lk($name)},
                            key($viaRelation->link) => $this->owner->{$this->lk($name)}
                        ]);

                        if ($model->insert()) {
                            $this->errors($record, $name, $index);
                            $res = false;
                        }
                    }
                }
            }

            // Remove via

            if ($records) {
                $this->owner->{$via} = $records;
                /** @var BaseActiveRecord $record */
                foreach ($this->owner->{$via} as $index => $record) {
                    $action = $record->getIsNewRecord() ? 'create' : 'update';
                    $this->allow($name, $action);
                    if (!$record->save()) {
                        $this->errors($record, $name, $index);
                        $res = false;
                    }
                }
            }
        }

        return $res;
    }

    public function beforeDelete(Event $event)
    {
        foreach ($this->names(false, false) as $name) {
            $this->allow($name, 'delete');
            if ($this->relation($name)->multiple) {
                foreach ($this->owner->{$name} as $record) {
                    $record->delete();
                }
            } else {
                $old = $this->owner->{$name};
                $old && $old->delete();
            }
        }
    }

    public function afterDelete(Event $event)
    {
        foreach ($this->names(true, false) as $name) {
            if ($this->dependsReverse($name)) {
                $old = $this->owner->{$name};
                $old && $old->delete();
            }
        }
    }

    ///
    /// Helpers
    ///

    protected function depends(string $name): bool
    {
        return !$this->via($name) && !$this->owner->isPrimaryKey((array)$this->rk($name));
    }

    protected function dependsReverse(string $name): bool
    {
        /** @var Schema $schema */
        $schema = $this->owner->db->schema;
        $indexes = $schema->findUniqueIndexes($this->owner->getTableSchema());
        foreach ($indexes as $index) {
            if ($index == [$this->rk($name)]) {
                return true;
            }
        }

        return false;
    }

    public function relation($name): ActiveQuery
    {
        static $cache = [];

        if (!isset($cache[$name])) {
            $cache[$name] = $this->owner->getRelation($name);
        }

        return $cache[$name];
    }

    /**
     * @param string $name
     * @return string[]
     */
    protected function pk(string $name): array
    {
        /** @var BaseActiveRecord|string $class */
        $class = $this->relation($name)->modelClass;
        return $class::primaryKey();
    }

    protected function lk(string $name): string
    {
        return key($this->relation($name)->link);
    }

    protected function rk(string $name): string
    {
        return current($this->relation($name)->link);
    }

    protected function via($name): ?string
    {
        if (!empty($this->via[$name])) {
            return $this->via[$name];
        }

        $relation = $this->relation($name);

        if ($relation->via) {
            if (!is_array($relation->via)) {
                return $name;
            }

            return $relation->via[0];
        }

        return null;
    }

    protected function set(array $data)
    {
        $names = $this->names(null, false);
        foreach ($data as $name => $value) {
            if (in_array($name, $names)) {
                $this->owner[$name] = $value;
            }
        }
    }

    /**
     * @param ActiveQueryInterface|string $relation
     * @param BaseActiveRecord[]|array $array
     * @return BaseActiveRecord[]
     */
    protected function models($relation, array $array): array
    {
        $models = [];
        foreach ($array as $data) {
            $models[] = $this->model($relation, $data);
        }
        return $models;
    }

    /**
     * @param ActiveQueryInterface|string $relation
     * @param BaseActiveRecord|array $data
     * @return BaseActiveRecord|null
     */
    protected function model($relation, $data): ?BaseActiveRecord
    {
        is_string($relation) && $relation = $this->relation($relation);

        if ($data) {
            if (!($data instanceof BaseActiveRecord)) {
                /** @var \yii2custom\common\core\ActiveRecord $model */
                $model = new $relation->modelClass();
                foreach ($data as $k => $v) {
                    if (!is_null($v) || !in_array($k, $model->primaryKey())) {
                        $model->{$k} = $v;
                    }
                }
                return $model;
            } else {
                return $data;
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @return null[]|BaseActiveRecord|BaseActiveRecord[]
     */
    protected function records(string $name)
    {
        $this->relation($name); // check relation exists
        $record = $this->owner->isRelationPopulated($name)
            ? $this->owner->{$name}
            : null;

        return $record
            ? ($this->relation($name)->multiple ? $record : [$record])
            : [];
    }

    protected function record(string $name): ?BaseActiveRecord
    {
        return $this->records($name)[0] ?? null;
    }

    protected function allow(string $name, string $action): bool
    {
        $rules = $this->scenarios[$this->owner->getScenario()][$name] ?? [];
        $rules = array_merge($this->relations[$name], $rules);
        if (!$rules[$action]) {
            throw new Exception('Action "' . $action . '" is not allowed for ' . get_class($this->owner) . '::' . $name);
        }

        return true;
    }

    /**
     * @param string $name
     * @param mixed|false $pk
     * @return ActiveQuery
     */
    protected function find(string $name, $pk = false): ActiveQuery
    {
        if ($pk !== false) {
            /** @var BaseActiveRecord|string $class */
            $class = $this->relation($name)->modelClass;
            /** @var ActiveQuery $query */
            $query = $class::find();
            $primaryKey = $class::primaryKey();

            if (!$primaryKey) {
                throw new InvalidConfigException('"' . $class . '" must have a primary key.');
            }

            if (count($primaryKey) > 1) {
                $conditions = [];
                foreach ($primaryKey as $item) {
                    $conditions[$item] = is_array($pk[$item]) ? array_values($pk[$item]) : $pk[$item];
                }
                $query->andWhere($conditions);
            } else {
                $query->where([$primaryKey[0] => is_array($pk) ? array_values($pk) : $pk]);
            }

            return $query;
        }

        return clone($this->relation($name));
    }

    /**
     * @param string $name
     * @return ActiveQuery
     */
    protected function column(string $name)
    {
        /** @var BaseActiveRecord|string $class */
        $class = $this->relation($name)->modelClass;
        return $this->find($name)->select($class::primaryKey())->asArray();
    }

    protected function errors(BaseActiveRecord $record, string $name, ?int $index = null)
    {
        foreach ($record->errors as $attribute => $errors) {
            foreach ($errors as $error) {
                $middle = is_null($index) ? '' : '[' . (string)$index . ']';
                $this->owner->addError($name . $middle . '.' . $attribute, $error);
            }
        }
    }

    ///
    /// Required validators
    ///

    protected function disableOwnerValidators()
    {
        $items = [];
        foreach ($this->names(true) as $name) {
            $items[$name] = $this->rk($name);
        }

        foreach ($this->owner->validators as $index => $validator) {
            if ($validator instanceof RequiredValidator) {
                foreach ($validator->attributes as $key => $attribute) {
                    if (($name = array_search($attribute, $items)) !== false) {
                        $this->diff['validators'][null][$index] = $attribute;
                        unset($validator->attributes[$key]);
                    }
                }
            }
        }
    }

    protected function disableRelatedValidators()
    {
        foreach ($this->names(false) as $name) {
            $rk = $this->lk($name);
            foreach ($this->records($name) as $record) {
                foreach ($record->validators as $index => $validator) {
                    if ($validator instanceof RequiredValidator) {
                        foreach ($validator->attributes as $key => $attribute) {
                            if ($attribute == $rk) {
                                $this->diff['validators'][$name][$index] = $attribute;
                                unset($validator->attributes[$key]);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function restoreOwnerValidators()
    {
        return $this->restoreValidators(null, [$this->owner]);
    }

    protected function restoreRelatedValidators()
    {
        foreach ($this->names(false) as $name) {
            if ($this->owner->isRelationPopulated($name)) {
                $this->restoreValidators($name, $this->records($name));
            }
        }
    }

    /**
     * @param string|null $name
     * @param BaseActiveRecord[] $records
     */
    protected function restoreValidators(?string $name, array $records)
    {
        foreach ($this->diff['validators'][$name] ?? [] as $index => $attributes) {
            foreach ($records as $record) {
                $record->validators[$index]->attributes[] = $attributes;
            }
        }

        unset($this->diff['validators'][$name]);
    }
}
