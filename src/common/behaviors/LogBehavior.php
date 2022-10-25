<?php

namespace yii2custom\common\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii2custom\common\core\ActiveRecord;

/**
 * @property ActiveRecord $owner
 */
class LogBehavior extends Behavior
{
    const SCHEMA_NAME = 'sys_log';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_CREATE = 'create';

    public $pass = [];

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_UPDATE => 'onBeforeUpdate',
            ActiveRecord::EVENT_AFTER_INSERT => 'onAfterInsert',
            ActiveRecord::EVENT_AFTER_DELETE => 'onAfterDelete',
        ];
    }

    ///
    /// Public
    ///

    public function updateAttributes(array $attributes)
    {
        $clone = clone($this->owner);
        $attrs = [];

        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $attrs[] = $value;
            } else {
                $clone->$name = $value;
                $attrs[] = $name;
            }
        }

        $values = $clone->getDirtyAttributes($attrs);

        if (count($values)) {
            $this->store(self::ACTION_UPDATE, $values, array_intersect_key($this->owner->oldAttributes, $values));
        }
    }

    ///
    /// Events
    ///

    public function onBeforeUpdate(Event $event)
    {
        if ($this->owner->oldAttributes) {
            $new = $this->owner->dirtyAttributes;
            $old = array_intersect_key($this->owner->oldAttributes, $this->owner->dirtyAttributes);
            $old && $new && $this->store(self::ACTION_UPDATE, $new, $old);
        }
    }

    public function onAfterInsert(Event $event)
    {
        $this->store(self::ACTION_CREATE, $this->owner->attributes);
    }

    public function onAfterDelete(Event $event)
    {
        $this->store(self::ACTION_DELETE, null, $this->owner->attributes);
    }

    ///
    /// Actions
    ///

    protected function store($action, $attributes, $old = null)
    {
        if ($this->pass()) {
            return;
        };

        !$this->tableExists() && $this->createTable();
        $this->insertRecord($action, $attributes, $old);
    }

    ///
    /// Helpers
    ///

    protected function insertRecord(string $action, ?array $attributes, $old = null): bool
    {
        return (bool)Yii::$app->db->createCommand()->insert($this->getTableName(), [
            'data' => $attributes,
            'old' => $old,
            'action' => $action,
            'manager_id' => Yii::$app->isAdmin() ? Yii::$app->user->id : null,
            'client_id' => Yii::$app->isApi() ? Yii::$app->user->id : null,
            'model_id' => $this->owner->id,
            'time' => time(),
        ])->execute();
    }

    protected function createTable()
    {
        $table = Yii::$app->db->quoteTableName($this->getTableName());
        $seq = Yii::$app->db->quoteTableName($this->getTableName() . '_id_seq');

        $sql[] = "create table if not exists $table (like sys_log._ including indexes)";
        $sql[] = "create sequence if not exists $seq owned by $table.id";
        $sql[] = "select setval('$seq'::regclass, coalesce(max(id), 0) + 1, false) from $table";
        $sql[] = "alter table $table alter column id set default nextval('$seq'::regclass)";

        foreach ($sql as $query) {
            Yii::$app->db->createCommand($query)->execute();
        }

        Yii::$app->db->schema->refreshTableSchema($this->getTableName());
    }

    protected function dropTable()
    {
        $table = $this->getTableName();
        $res = Yii::$app->db->createCommand()->dropTable($table)->execute();
        Yii::$app->db->schema->refreshTableSchema($table);
    }

    protected function tableExists(): bool
    {
        return (Yii::$app->db->getTableSchema($this->getTableName()) !== null);
    }

    protected function getTableName(): string
    {
        return self::SCHEMA_NAME . '.' . str_replace('.', '_', $this->owner::tableName());
    }

    protected function pass(): bool
    {
        if (!in_array(self::SCHEMA_NAME, Yii::$app->db->schema->getSchemaNames())) {
            return true;
        }

        if (!Yii::$app->isApi() && !Yii::$app->isAdmin()) {
            return true;
        }

        if (!Yii::$app->user->id) {
            return true;
        }

        return in_array($this->owner::getTableSchema()->schemaName, $this->pass);
    }
}