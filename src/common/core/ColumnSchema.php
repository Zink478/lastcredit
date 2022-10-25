<?php

namespace yii2custom\common\core;

use tigrov\pgsql\Schema;

class ColumnSchema extends \tigrov\pgsql\ColumnSchema
{
    /**
     * Ignore date fields
     *
     * @inheritDoc
     */
    protected function phpTypecastValue($value)
    {
        if ($value === null) {
            return null;
        }

        switch ($this->type) {
            case Schema::TYPE_TIMESTAMP:
            case Schema::TYPE_TIME:
            case Schema::TYPE_DATE:
            case Schema::TYPE_DATETIME:
                return $this->typecast($value);
        }

        return parent::phpTypecastValue($value);
    }

    /**
     * Ignore date fields
     *
     * @inheritDoc
     */
    public function dbTypecastValue($value)
    {
        if ($value === null) {
            return null;
        }

        switch ($this->type) {
            case Schema::TYPE_TIMESTAMP:
            case Schema::TYPE_DATETIME:
            case Schema::TYPE_DATE:
            case Schema::TYPE_TIME:
                return $this->typecast($value);
        }

        return parent::dbTypecastValue($value);
    }
}