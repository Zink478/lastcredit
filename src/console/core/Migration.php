<?php

namespace yii2custom\console\core;

class Migration extends \yii\db\Migration
{
    const CASCADE = 'CASCADE';
    const RESTRICT = 'RESTRICT';
    const SET_NULL = 'SET NULL';
    const NO_ACTION = 'NO ACTION';

    public $s = '"';

    public function init()
    {
        parent::init();

        if ($this->isMy()) {
            $this->s = '`';
        }
    }

    ///
    /// Tables
    ///

    /**
     * @inheritDoc
     */
    public function createTable($table, $columns, $options = null)
    {
        $options = $options ?? ($this->isMy() ? 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB' : null);
        parent::createTable($table, $columns, $options);
        $this->isPg() && $this->log([$table]);
    }

    /**
     * @inheritDoc
     */
    public function dropTable($table)
    {
        parent::dropTable($table);

        $log = 'log.' . str_replace('.', '_', $table);
        $this->execute('DROP TABLE IF EXISTS ' . $this->escape($log));
    }

    /**
     * @inheritDoc
     */
    public function truncateTable($table, $cascade = false)
    {
        if ($cascade) {
            $time = $this->beginCommand("truncate table $table cascade");
            $cmd = $this->db->createCommand();
            $sql = $cmd->db->getQueryBuilder()->truncateTable($table);
            $sql = strpos($sql, 'RESTART IDENTITY')
                ? str_replace('RESTART IDENTITY', 'RESTART IDENTITY CASCADE', $sql)
                : ($sql . ' CASCADE');
            $cmd->setSql($sql)->execute();
            $this->endCommand($time);
        } else {
            parent::truncateTable($table);
        }
    }

    public function reset($table, int $value = 1, string $column = 'id')
    {
        if (!$this->isPg()) {
            throw new \yii\db\Exception('Not supported for:' . $this->db->driverName);
        }

        if ($column === true) {
            $column = 'id';
        }

        $seq = $table . '_' . $column . '_seq';
        $time = $this->beginCommand("select setval('$seq', $value)");
        $this->db->createCommand()->truncateTable($table)->execute();
        $this->endCommand($time);
    }

    ///
    /// Schemas
    ///

    /**
     * @param $schema
     */
    public function createSchema(string $schema)
    {
        if (!$this->isPg()) {
            throw new \yii\db\Exception('Not supported for:' . $this->db->driverName);
        }

        $time = $this->beginCommand("create schema $schema");
        $this->db->createCommand()->setSql("create schema $schema")->execute();
        $this->endCommand($time);
    }

    /**
     * @param string $schema
     * @param bool $cascade
     */
    public function dropSchema(string $schema, $cascade = false)
    {
        if (!$this->isPg()) {
            throw new \yii\db\Exception('Not supported for:' . $this->db->driverName);
        }

        $time = $this->beginCommand("drop schema $schema" . ($cascade ? ' cascade' : ''));
        $this->db->createCommand()->setSql("drop schema $schema" . ($cascade ? ' cascade' : ''))->execute();
        $this->endCommand($time);
    }

    ///
    /// Views
    ///

    /**
     * @param string $table
     * @param string $query
     */
    public function createView($table, $query)
    {
        $view = 'vw_' . $table;
        $time = $this->beginCommand("create view $view");
        $this->db->createCommand()->createView($view, $query)->execute();
        $this->endCommand($time);
    }

    /**
     * @param string $table
     */
    public function dropView($table)
    {
        $view = 'vw_' . $table;
        $time = $this->beginCommand("drop view $view");
        $this->db->createCommand()->dropView($view)->execute();
        $this->endCommand($time);
    }

    ///
    /// Keys
    ///

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function addCustomPrimaryKey($table, $columns = ['id'])
    {
        $name = $this->key($table, [], 'pk');
        parent::addPrimaryKey($name, $table, $columns);
    }

    /**
     * @param string $table
     * @param string $refTable
     * @param string|string[]|null $columns
     * @param string|string[] $refColumns
     * @param string|null $delete
     * @param string|null $update
     */
    public function addCustomForeignKey(
        $table,
        $refTable,
        $columns = null,
        $refColumns = 'id',
        $delete = self::RESTRICT,
        $update = self::RESTRICT
    )
    {
        if (is_null($columns)) {
            $columns = self::table($refTable) . '_id';
        }

        $name = $this->key($table, $columns, 'fk');
        parent::addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }

    /**
     * @param string $table
     * @param string $refTable
     * @param string|string[]|null $columns
     */
    public function dropCustomForeignKey(
        $table,
        $refTable,
        $columns = null
    )
    {
        if (is_null($columns)) {
            $columns = self::table($refTable) . '_id';
        }

        $name = $this->key($table, $columns, 'fk');
        parent::dropForeignKey($name, $table);
    }

    /**
     * @deprecated
     */
    public function addUniqueKey(string $table, $columns)
    {
        return $this->addCustomUniqueIndex($table, $columns);
    }

    /**
     * @deprecated
     */
    public function addCustomUniqueKey(string $table, $columns)
    {
        return $this->addCustomUniqueIndex($table, $columns);
    }

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function addCustomUniqueIndex(string $table, $columns)
    {
        $s = $this->s;
        $name = $this->key($table, $columns, 'unq', 'idx');
        $this->execute("ALTER TABLE " . $this->escape($table) . " ADD CONSTRAINT " . $s . $name . $s . " UNIQUE(" . join(',', $this->quote($columns)) . ')');
    }

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function dropUniqueKey($table, $columns)
    {
        return $this->dropKey($table, $this->key($table, $columns, 'unq', 'key'));
    }

    /**
     * @param string $table
     * @param string $key
     */
    public function dropKey($table, $key)
    {
        return $this->execute("ALTER TABLE " . $this->escape($table) . " DROP CONSTRAINT \"$key\"");
    }

    ///
    /// Indexes
    ///

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function addCustomIndex($table, $columns)
    {
        $name = $this->key($table, $columns, 'idx');
        return $this->createIndex($name, $table, $columns);
    }

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function dropCustomIndex($table, $columns)
    {
        $name = $this->key($table, $columns, 'idx');
        return $this->dropIndex($name, $table);
    }

    ///
    /// Unique
    ///

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function addUniqueIndex($table, $columns, $conditions = '')
    {
        $where = $conditions ? ' WHERE ( ' . $conditions . ' ) ' : '';
        $name = $this->key($table, $columns, 'unq', 'idx');
        return $this->createIndex($name, $table, $columns, true) . $where;
    }

    /**
     * @param string $table
     * @param string|string[] $columns
     */
    public function dropUniqueIndex($table, $columns)
    {
        $name = $this->key($table, $columns, 'unq', 'idx');
        return $this->dropIndex($name, $table);
    }

    ///
    /// Types
    ///

    /**
     * @return void|\yii\db\ColumnSchemaBuilder
     */
    public function jsonb()
    {
        return $this->custom('jsonb');
    }

    public function money($precision = 10, $scale = 2)
    {
        return parent::money($precision, $scale);
    }

    /**
     * @param string $type
     * @param int|null $length
     * @return \yii\db\ColumnSchemaBuilder
     * @throws \yii\base\NotSupportedException
     */
    public function custom($type, $length = null)
    {
        return $this->getDb()->getSchema()->createColumnSchemaBuilder($type, $length);
    }

    ///
    /// Helpers
    ///

    public function isMy()
    {
        return $this->db->driverName === 'mysql';
    }

    public function isPg()
    {
        return $this->db->driverName === 'pgsql';
    }

    public function log(array $tables)
    {
        $sql[] = '
            CREATE OR REPLACE FUNCTION jsonb_diff(val1 JSONB, val2 JSONB)
            RETURNS JSONB AS $$
            DECLARE
              result JSONB;
              v RECORD;
            BEGIN
               result = val1;
               FOR v IN SELECT * FROM jsonb_each(val2) LOOP
                 IF result @> jsonb_build_object(v.key,v.value) THEN
                   result = result - v.key;
                 ELSIF result ?? v.key THEN
                   CONTINUE;
                 ELSE
                   result = result || jsonb_build_object(v.key, \'null\');
                 END IF;
               END LOOP;
               RETURN result;
            END;
            $$ LANGUAGE plpgsql';

        $sql[] = "
            CREATE OR REPLACE FUNCTION log() RETURNS trigger AS $$
              DECLARE tbl text;
            BEGIN
              tbl := 'log.' || TG_RELNAME;
              CREATE SCHEMA IF NOT EXISTS log;
              EXECUTE 'CREATE TABLE IF NOT EXISTS ' || tbl || ' (id bigserial, time timestamp DEFAULT now(), operation char, pk integer, data jsonb)';
              IF TG_OP = 'INSERT' THEN
                EXECUTE 'INSERT INTO ' || tbl || ' (operation, pk, data) VALUES (''I'', $1.id, to_jsonb($1))' USING NEW;
                RETURN NEW;
              ELSIF  TG_OP = 'UPDATE' THEN
                EXECUTE 'INSERT INTO ' || tbl || ' (operation, pk, data) VALUES (''U'', $1.id, jsonb_diff(to_jsonb($1), to_jsonb($2)))' USING NEW, OLD;
                RETURN NEW;
              ELSIF TG_OP = 'DELETE' THEN
                EXECUTE 'INSERT INTO ' || tbl || ' (operation, pk, data) VALUES (''D'', $1.id, to_jsonb($1))' USING OLD;
                RETURN OLD;
              END IF;
            END;
            $$ LANGUAGE 'plpgsql' SECURITY DEFINER;
        ";

        foreach ($tables as $table) {
            $name = str_replace('.', '_', $table) . '_log';
            $sql[] = 'DROP TRIGGER IF EXISTS "' . $name . '" ON ' . $this->escape($table);
            $sql[] = 'CREATE TRIGGER "' . $name . '" BEFORE INSERT OR UPDATE OR DELETE ON ' . $this->escape($table) . ' FOR EACH ROW EXECUTE PROCEDURE log()';
        }

        foreach ($sql as $cmd) {
            $this->execute($cmd);
        }
    }

    /**
     * @param string $table
     * @param string[]|string $columns
     * @param string|null $prefix
     * @param string|null $suffix
     * @return string
     */
    private function key($table, $columns, $prefix = null, $suffix = null)
    {
        $result = self::table($table) . '-' . join('-', (array)$columns);
        return trim(str_replace('--', '-', ($prefix ? $prefix . '-' : '') . $result . ($suffix ? '-' . $suffix : '')), '-'); // TODO simplify
    }

    /**
     * @param $table
     * @return string
     */
    private function table($table)
    {
        $result = self::schemaAndTable($table);
        return $result[1];
    }

    /**
     * @param $table
     * @return string[]
     */
    private function schemaAndTable($table)
    {
        $table = preg_replace(['/^{{%/', '/}}$/'], '', $table);
        $schema = null;
        if (strpos($table, '.') !== false) {
            [$schema, $table] = explode('.', $table);
        }
        return [$schema, $table];
    }

    private function escape($table)
    {
        return $this->s . str_replace('.', "{$this->s}.{$this->s}", $table) . $this->s;
    }

    private function quote($data)
    {
        if (is_string($data)) {
            $data = [$data];
        }

        foreach ($data as &$value) {
            $value = $this->s . $value . $this->s;
        }

        return $data;
    }
}