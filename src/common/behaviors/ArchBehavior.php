<?php

namespace yii2custom\common\behaviors;

use common\models\SysArch;
use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii2custom\common\core\ActiveRecord;

/**
 * @property ActiveRecord $owner
 */
class ArchBehavior extends Behavior
{
    const SCHEMA_NAME = 'sys_arch';
    public $pass = [];

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_AFTER_DELETE => 'onAfterDelete',
        ];
    }

    ///
    /// Events
    ///

    public function onAfterDelete(Event $event)
    {
        $this->store($this->owner->attributes);
    }

    ///
    /// Actions
    ///

    protected function store($attributes)
    {
        if ($this->pass()) {
            return;
        };

        try {
            !$this->tableExists() && $this->createTable();
            $this->insertRecord($attributes);
        } catch (\Throwable $e) {
            Yii::warning($e->getMessage(), 'model-log');
        }
    }

    ///
    /// Helpers
    ///

    protected function insertRecord(?array $attributes): bool
    {
        return (bool)Yii::$app->db->createCommand()->insert($this->getTableName(), [
            'data' => $attributes,
            'manager_id' => Yii::$app->isAdmin() ? Yii::$app->user->id : null,
            'client_id' => Yii::$app->isApi() ? Yii::$app->user->id : null,
            'model_id' => $this->owner->id,
            'time' => time(),
        ])->execute();
    }

    protected function createTable(): bool
    {
        $tableName = $this->getTableName();
        $res = Yii::$app->db->createCommand()->createTable($tableName, [
            'id' => 'serial primary key',
            'model_id' => 'int not null',
            'data' => 'jsonb',
            'client_id' => 'int',
            'manager_id' => 'int',
            'time' => 'int not null'
        ])->execute();

        [$schema, $table] = explode('.', $tableName);
        Yii::$app->db->createCommand()->createIndex('idx-' . $table . '-model_id', $tableName, 'model_id')->execute();
        Yii::$app->db->createCommand()->createIndex('idx-' . $table . '-manager_id', $tableName, 'manager_id')->execute();
        Yii::$app->db->createCommand()->createIndex('idx-' . $table . '-client_id', $tableName, 'client_id')->execute();
        Yii::$app->db->schema->refreshTableSchema($tableName);

        return (bool)$res;
    }

    protected function dropTable(): bool
    {
        $tableName = $this->getTableName();
        $res = Yii::$app->db->createCommand()->dropTable($tableName)->execute();

        if ($res) {
            Yii::$app->db->schema->refreshTableSchema($tableName);
        }

        return (bool)$res;
    }

    protected function tableExists(): bool
    {
        return (Yii::$app->db->getTableSchema($this->getTableName()) !== null);
    }

    protected function getTableName(): string
    {
        return SysArch::getTableName($this->owner::tableName());
    }

    protected function pass(): bool
    {
        if (!Yii::$app->isApi() && !Yii::$app->isAdmin()) {
            return true;
        }

        if (!Yii::$app->user->id) {
            return true;
        }

        return in_array($this->owner::getTableSchema()->schemaName, $this->pass);
    }
}


