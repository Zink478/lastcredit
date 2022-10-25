<?php

namespace yii2custom\common\traits\ActiveRecord;

use yii\base\Exception;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\core\Model;
use yii2custom\common\helpers\RuleHelper;

/**
 * @mixin Model
 */
trait TSchema
{
    public function refs()
    {
        $refs = [];
        $schema = $this->schema();
        $attributes = [];

        foreach (['grid', 'form'] as $index) {
            foreach ($schema[$index] as $key => $value) {
                if (is_string($key)) {
                    if (is_array($value) && count($value) == 1 && ArrayHelper::isIndexed($value) && is_array($value[0])) {
                        foreach ($value[0] as $k => $v) {
                            $attributes[] = is_string($k) ? $k : (is_array($v) ? $v[0] : $v);
                        }
                    } else {
                        $attributes[] = $key;
                    }
                } elseif (is_array($value)) {
                    $attributes[] = $value[0];
                } else {
                    $attributes[] = $value;
                }
            }
        }

        foreach ($attributes as $attribute) {
            $name = Inflector::camel2id(cl(static::class));

            if ($attribute == 'id') {
                $refs[$attribute] = 'id';
            } else if ($attribute == 'password') {
                $refs[$attribute] = 'password';
            } else if ($attribute == 'file') {
                $refs[$attribute] = 'file';
            } else if ($attribute == static::titleAttribute()) {
                $refs[$attribute] = ['text', ['link' => $name]];
            } else if (str_ends_with($attribute, '_at')) {
                $refs[$attribute] = 'time';
            } else if ($attribute == 'date' || str_ends_with($attribute, '_date')) {
                $refs[$attribute] = 'date';
            } else if (static::enum($attribute)) {
                $refs[$attribute] = ['list', ['items' => $name . '.' . $attribute]];
            } else if (RuleHelper::get($this->rules(), $attribute, 'boolean')) {
                $refs[$attribute] = 'bool';
            } else if (in_array($attribute, $this->relations())) {
                $rel = $this->getRelation($attribute);
                $refs[$attribute] = [$rel->multiple ? 'grid' : 'relation', ['name' => Inflector::camel2id(cl($rel->modelClass))]];
            } else if (str_ends_with($attribute, '_id')) {
                $relation = lcfirst(Inflector::camelize(substr($attribute, 0, -3)));
                if (($rel = $this->getRelation($relation, false)) !== null) {
                    $refs[$attribute] = ['list', ['items' => 'list.' . Inflector::camel2id(cl($rel->modelClass))]];
                }
            }
        }

        return $refs;
    }

    public function schema()
    {
        $label = Inflector::titleize(cl(TActiveRecord::modelClass(static::class)), true);
        $name = Inflector::camel2id(Inflector::camelize(cl(TActiveRecord::modelClass(static::class))));
        $relations = [];

        foreach ($this->relations() as $item) {
            $relation = $this->getRelation($item);
            if ($relation->multiple) {
                $relations[$item] = ['required' => false, 'multiple' => true];
            } else {
                $model = $this;
                $attribute = current($relation->link);
                while (TActiveRecord::isSearchModel($model::class)) {
                    $class = get_parent_class($model);
                    $model = new $class;
                }

                if (RuleHelper::get($model::rules(), $attribute, 'required')) {
                    $relations[$item] = ['required' => true, 'multiple' => false];
                } else {
                    $relations[$item] = ['required' => false, 'multiple' => false];
                }
            }
        }

        return [
            'name' => $name,
            'label' => Inflector::singularize($label),
            'plural' => Inflector::pluralize($label),
            'actions' => [],
            'relations' => $relations,
            'grid' => [],
            'form' => [],
            'allow' => ['index', 'create', 'view', 'update', 'delete'],
        ];
    }

    public function formattedSchema()
    {
        /** @var ActiveRecord $model */
        $model = $this;
        $search = null;

        if (TActiveRecord::isSearchModel($model::class)) {
            $search = $model;
            $model = new (get_parent_class($search))();
        }

        $schema = $this->typeSchema($model);

        if (!method_exists($model, 'schema')) {
            throw new Exception($class . '::search() is not defined.');
        }

        ///
        /// Grid
        ///

        if ($search) {
            /** @var ActiveDataProvider $provider */
            $provider = $search->{'search'}([]);
            $sortable = array_keys($provider->sort->attributes);
            $attributes = $search->activeAttributes();

            foreach ($schema['grid'] as $key => $value) {
                if (isset($schema['grid'][$key]['filter'])) {
                    $this->typeSchemaItem($key, $schema['grid'][$key]['filter']);
                } else {
                    $schema['grid'][$key]['filter'] = in_array($value['name'], $attributes);
                }
                $schema['grid'][$key]['sortable'] = in_array($value['name'], $sortable);
            }
        }

        $schema['cols'] = $schema['cols'] ?? 1;
        return $schema;
    }

    private function typeSchema(ActiveRecord|Model|string $model, array $schema = [], $root = true)
    {
        $result = [];
        $reserved = ['grid', 'form'];
        $items = $root ? $reserved : [];
        if (is_string($model)) {
            $model = new $model();
        }

        $refs = $model->refs();
        $schema = $schema ?: $model->schema();

        foreach ($schema as $key => $value) {
            if ($items && !in_array($key, $items)) {
                $result[$key] = $value;
                continue;
            } else if (!$value) {
                $result[$key] = new \stdClass();
                continue;
            } elseif (is_numeric($key) && is_string($value)) {
                if (!empty($refs[$value])) {
                    $key = $value;
                    $value = $refs[$value];
                }
            } else if (is_string($key) && !in_array($key, $reserved) && is_array($value) && is_string(key($value))) {
                // attrubutes without type

                if (!empty($refs[$key])) {
                    $ref = $refs[$key];
                    if (is_string($ref)) {
                        $ref = [$ref, $value];
                    } else {
                        $ref[1] = array_merge($ref[1], $value);
                    }

                    $value = $ref;
                } else {
                    $value = ['text', $value];
                }
            }

            $this->typeSchemaItem($key, $value);
            $type = is_string($value['type'] ?? null) ? $value['type'] : null;

            if (is_array($value)) {
                $name = $key;
                if (($value['type'] ?? null) == 'relation') {
                    $relation = lcfirst(Inflector::camelize($key));
                    $name = Inflector::camel2id(cl($model->getRelation($relation)->modelClass));
                }

                $result[$key] = ($value && !$type)
                    ? $this->typeSchema($model, $value, false)
                    : array_merge(['name' => $name, 'label' => ucwords($model->getAttributeLabel($key))], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function typeSchemaItem(&$key, &$value)
    {
        if (is_string($value)) {
            if (is_int($key)) {
                $key = $value;
                $value = [];
            } else {
                $value = ['type' => $value];
            }
        } else if (is_string($key) && is_array($value)) {
            if (count($value) == 2 && is_string($value[0] ?? false) && is_array($value[1] ?? false)) {
                $value = array_filter(['type' => $value[0], ...$value[1]]);
                foreach ($value as $k => $v) {
                    if (is_integer($k) && $v && is_string($v)) {
                        unset($value[$k]);
                        $value[$v] = true;
                    }
                }
            } else if (count($value) == 1 && is_array($value[0] ?? false)) {
                $value = $value[0];
            }
        }
    }
}