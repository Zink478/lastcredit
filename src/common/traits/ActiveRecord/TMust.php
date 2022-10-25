<?php

namespace yii2custom\common\traits\ActiveRecord;

use yii\base\Exception;
use yii2custom\common\core\ActiveRecord;

/**
 * @mixin ActiveRecord
 */
trait TMust
{
    /**
     * @param array|string|integer $condition
     * @return static[]
     * @throws Exception
     */
    public static function mustFindAll($condition = null): array
    {
        $result = static::findAll($condition);

        if (!$result) {
            throw new Exception("Records not found.");
        }

        return $result;
    }

    /**
     * @param array|string|integer $condition
     * @param array|string|null $with
     * @return static
     * @throws Exception
     */
    public static function mustFindOne($condition, $with = [])
    {
        $result = static::findOne($condition, $with);

        if (!$result) {
            $name = cl(static::class);
            throw new Exception("Record for model $name not found.");
        }

        return $result;
    }

    /**
     * @param bool $runValidation
     * @param string[]|null $attributeNames
     * @return bool
     * @throws Exception
     */
    public function mustSave(bool $runValidation = true, ?array $attributeNames = null)
    {
        $result = $this->save($runValidation, $attributeNames);

        if ($this->errors) {
            throw new Exception(cl(get_called_class()) . " saving errors: " . join(" | ", $this->getErrorSummary(false)));
        }

        return $result;
    }

    /**
     * @param bool $runValidation
     * @param string[]|null $attributes
     * @return bool
     * @throws Exception
     */
    public function mustInsert(bool $runValidation = true, ?array $attributes = null)
    {
        $result = $this->insert($runValidation, $attributes);

        if ($this->errors) {
            throw new Exception(get_called_class() . " inserting errors: " . join(" | ", $this->getErrorSummary(false)));
        }

        return $result;
    }

    /**
     * @param bool $runValidation
     * @param string[]|null $attributeNames
     * @return int|false
     * @throws Exception
     */
    public function mustUpdate(bool $runValidation = true, ?array $attributeNames = null)
    {
        $result = $this->update($runValidation, $attributeNames);

        if ($this->errors) {
            throw new Exception(get_called_class() . " updating errors: " . join(" | ", $this->getErrorSummary(false)));
        }

        return $result;
    }

    /**
     * @param string[]|null $attributeNames
     * @param bool $clearErrors
     * @return bool
     * @throws Exception
     */
    public function mustValidate($attributeNames = null, $clearErrors = true)
    {
        $result = $this->validate($attributeNames, $clearErrors);

        if ($this->errors) {
            throw new Exception(get_called_class() . " validation errors: " . join(" | ", $this->getErrorSummary(false)));
        }

        return $result;
    }
}