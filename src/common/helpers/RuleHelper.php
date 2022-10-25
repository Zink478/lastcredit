<?php

namespace yii2custom\common\helpers;

use yii\base\Exception;

class RuleHelper
{
    public static function get($rules, string $attribute, string $type = null): ?array
    {
        foreach ($rules as $rule) {
            $rule[0] = is_array($rule[0]) ? $rule[0] : [$rule[0]];
            if (in_array($attribute, $rule[0]) && (!$type || $type == $rule[1])) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $attribute
     * @param string $type
     * @throws Exception
     */
    public static function remove(array &$rules, string $attribute, string $type)
    {
        foreach ($rules as $key => &$rule) {
            if (($rule[0] ?? null) && ($rule[1] ?? null) && $rule[1] == $type) {
                if (in_array($attribute, (array)$rule[0])) {
                    $rule[0] = array_diff((array)$rule[0], [$attribute]);
                    if (!$rule[0]) {
                        unset($rules[$key]);
                    }
                    return;
                }
            }
        }

        throw new Exception("Cannot remove attribute $attribute with type: $type");
    }

    /**
     * @deprecated
     */
    public static function removeAttribute(array &$rules, string $attribute, string $type)
    {
        return static::remove($rules, $attribute, $type);
    }

    /**
     * @param array $rules
     * @param string[] $types
     * @return string[]
     */
    public static function attributes(array $rules, $types = []): array
    {
        $result = [];

        if (is_string($types)) {
            $types = [$types];
        }

        foreach ($rules as $rule) {
            $result = array_merge($result, (array)$rule[0]);
        }

        return array_unique($result);
    }

    /**
     * @param array $rules
     * @param string[] $attributes
     * @return array
     */
    public static function filter(array $rules, array $attributes): array
    {
        foreach ($rules as $key => &$rule) {
            $rule[0] = array_intersect((array)$rule[0], $attributes);
            if (!$rule[0]) {
                unset($rules[$key]);
            }
        }

        return $rules;
    }
}