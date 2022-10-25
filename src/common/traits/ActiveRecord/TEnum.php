<?php

namespace yii2custom\common\traits\ActiveRecord;

use yii\helpers\Inflector;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\core\Model;

/**
 * Provide enums by class constant lists
 *
 * @mixin Model
 */
trait TEnum
{
    public function title($group, $value = null): ?string
    {
        $value = $value ?? $this->{$group};
        return static::enum($group)[$value] ?? null;
    }

    public static function enum($group): array
    {
        $titles = self::titles($group);
        $reflect = new \ReflectionClass(static::class);
        if ($parent = $reflect->getParentClass()) {
            $constants = array_merge($parent->getConstants(), $reflect->getConstants());
        } else {
            $constants = $reflect->getConstants();
        }

        $result = [];
        $key = strtoupper(Inflector::underscore(Inflector::id2camel(strtolower($group))));

        foreach ($constants as $name => $value) {
            if (substr($name, 0, strlen($key) + 1) == $key . '_') {
                $title = Inflector::camel2words(Inflector::camelize(strtolower(substr($name, strlen($key)))));
                $result[$value] = $titles[$value] ?? $title;
            }
        }

        return $result;
    }

    public static function hints($group)
    {
        $reflect = new \ReflectionClass(static::class);
        if ($parent = $reflect->getParentClass()) {
            $constants = array_merge($parent->getReflectionConstants(), $reflect->getReflectionConstants());
        } else {
            $constants = $reflect->getReflectionConstants();
        }

        $result = [];
        $key = strtoupper(Inflector::underscore(Inflector::id2camel(strtolower($group))));

        foreach ($constants as $constant) {
            $name = $constant->getName();
            $value = $constant->getValue();

            if (substr($name, 0, strlen($key) + 1) == $key . '_') {
                if (($doc = $constant->getDocComment()) !== false) {
                    if (strpos($doc, "\n") === false) {
                        $doc = "/**\n* " . substr($doc, 4, -2) . "\n*/";
                    }
                    $parsed = new \Zend_Reflection_Docblock($doc);
                    if ($parsed->hasTag('desc')) {
                        $result[$value] = trim($parsed->getTag('desc')->getDescription());
                    }
                }
            }
        }

        return $result;
    }

    private static function titles($group)
    {
        $reflect = new \ReflectionClass(static::class);
        if ($parent = $reflect->getParentClass()) {
            $constants = array_merge($parent->getReflectionConstants(), $reflect->getReflectionConstants());
        } else {
            $constants = $reflect->getReflectionConstants();
        }

        $result = [];
        $key = strtoupper(Inflector::underscore(Inflector::id2camel(strtolower($group))));

        foreach ($constants as $constant) {
            $name = $constant->getName();
            $value = $constant->getValue();

            if (substr($name, 0, strlen($key) + 1) == $key . '_') {
                if (($doc = $constant->getDocComment()) !== false) {
                    if (strpos($doc, "\n") === false) {
                        $doc = "/**\n* " . substr($doc, 4, -2) . "\n*/";
                    }
                    $parsed = new \Zend_Reflection_Docblock($doc);
                    if ($parsed->getShortDescription()) {
                        $result[$value] = $parsed->getShortDescription();
                    }
                }
            }
        }

        return $result;
    }
}