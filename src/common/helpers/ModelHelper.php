<?php

namespace yii2custom\common\helpers;

use yii\base\Model;
use yii2custom\common\core\ActiveRecord;

class ModelHelper
{
    public static function combine(array ...$arrays)
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_string($key)) {
                    $result[] = $key;
                } else if (is_string($value)) {
                    $result[] = $value;
                }
            }
        }

        $result = array_unique($result);
        return array_combine($result, $result);
    }

    public static function decombine($array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = is_string($value) ? $value : $key;
        }
        return $result;
    }

    public static function column(string $table, string $attribute): string
    {
        if (!str_contains($attribute, '.')) {
            $attribute = $table . '.' . $attribute;
        }

        return $attribute;
    }

    public static function columns(string $table, array $attributes): array
    {
        foreach ($attributes as &$attribute) {
            $attribute = static::column($table, $attribute);
        }

        return $attributes;
    }

    public static function alias($name = 't')
    {
        static $c = 0;
        return $name . $c++;
    }

    public static function compare(ActiveRecord $model, string $attribute, $value = null, $new = null, $strict = false): bool
    {
        $oldValue = $model->getOldAttribute($attribute);
        $newValue = $model->getAttribute($attribute);

        if ($strict) {
            if ($oldValue === $newValue) {
                return false;
            }

            if ($oldValue !== $value) {
                return false;
            }

            if ($newValue !== $new) {
                return false;
            }
        } else {
            if (!is_null($value) && is_null($new)) {
                $new = $value;
                $value = null;
            }

            if ($oldValue == $newValue) {
                return false;
            }

            if (!is_null($value) && $oldValue != $value) {
                return false;
            }

            if (!is_null($new) && $newValue != $new) {
                return false;
            }
        }

        return true;
    }

    public static function value(Model $model, string $path)
    {
        $result = $model;
        foreach (array_filter(explode('.', $path)) as $attribute) {
            $result = $result->{$attribute};
        }

        return $result;
    }
}