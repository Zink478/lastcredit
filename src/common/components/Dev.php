<?php

namespace yii2custom\common\components;

use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;
use phpDocumentor\Reflection\Type;
use Yii;
use yii\base\BaseObject;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;
use yii2custom\common\core\ActiveRecord;

class Dev extends Component
{
    protected $project = 'app';
    public $search = false;

    public function init()
    {
        parent::init();
        $this->project = substr(Yii::$app->id, strlen('app-'));
    }

    /**
     * @param string $project
     * @return string[]
     */
    public function generateModels(): array
    {
        $result = [];
        $dir = Yii::getAlias('@' . $this->project . '/models');
        foreach (FileHelper::findFiles($dir, ['recursive' => false]) as $fn) {
            $name = pathinfo($fn, PATHINFO_FILENAME);
            $result[] = $this->generateModel($name);
        }

        return $result;
    }

    /**
     * @param string $project
     * @return string[]
     */
    public function generateSearch(): array
    {
        $this->search = true;
        $result = [];

        $dir = Yii::getAlias('@' . $this->project . '/models/search');

        foreach (FileHelper::findFiles($dir, ['recursive' => false]) as $fn) {
            $name = pathinfo($fn, PATHINFO_FILENAME);
            $result[] = $this->generateModelSearch($name);
        }

        $this->search = false;
        return $result;
    }

    ///
    /// Protected
    ///

    protected function generateModel(string $name)
    {
        /** @var ActiveRecord $model */
        $result = [];
        $result[] = "export interface I$name {";
        foreach ($this->fields($name) as $field) {
            $result[] = '  ' . $field->ts();
        }

        foreach ($this->relations($name) as $relation) {
            $result[] = '  ' . $relation->ts();
        }
        $result[] = '}';

        return join("\n", $result);
    }

    protected function generateModelSearch(string $name)
    {
        /** @var ActiveRecord $model */
        $result = [];
        $result[] = "export interface I$name {";
        foreach ($this->properties($name, $this->model($name)->safeAttributes()) as $field) {
            $result[] = '  ' . $field->ts();
        }
        $result[] = '}';


        return join("\n", $result);
    }

    protected function fields(string $name)
    {
        $filter = [];
        foreach ($this->model($name)->fields() as $key => $value) {
            if (is_string($key)) {
                $filter[] = $key;
            } else if (is_string($value)) {
                $filter[] = $value;
            }
        }

        return $this->properties($name, $filter);
    }

    protected function attributes(string $name)
    {
        return $this->properties($name, $this->model($name)->attributes());
    }

    protected function relations(string $name)
    {
        $filter = [];
        foreach ($this->model($name)->extraFields() as $key => $value) {
            if (is_string($key)) {
                $filter[] = $key;
            } else if (is_string($value)) {
                $filter[] = $value;
            }
        }

        return $this->properties($name, $filter);
    }

    /**
     * @param $name
     * @param array $filter
     * @return \api\controllers\TProperty[]
     */
    protected function properties($name, $filter = [])
    {
        $factory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        $class = new \ReflectionClass($this->class($name));
        $items = [];

        while ($class) {

            $publicProperties = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

            if ($publicProperties) {
                $properties = [];
                foreach ($publicProperties as $publicProperty) {
                    if (!in_array($publicProperty->getName(), $filter)) {
                        continue;
                    }

                    $doc = $publicProperty->getDocComment();
                    if ($doc) {
                        if (strpos($doc, "\n") === false) {
                            $doc = "/**\n* " . substr($doc, 4, -2) . "\n*/";
                        }

                        $block = new \Zend_Reflection_Docblock($doc);
                        $desc = trim($block->getTag('var')->getDescription());
                        $properties[$publicProperty->name] = $desc;
                    } else {
                        // TODO calc type
                    }
                }

                $items[] = $properties;
            }

            $doc = $class->getDocComment();

            if ($doc) {
                $docClass = $factory->create($doc);

                $docProperties = array_merge(
                    $docClass->getTagsByName('property'),
                    $docClass->getTagsByName('property-read'),
                    $docClass->getTagsByName('property-write')
                );

                $docProperties = array_filter($docProperties, function ($v) use ($filter) {
                    /** @var Property|PropertyRead|PropertyWrite $v */
                    return in_array($v->getVariableName(), $filter);
                });

                if ($docProperties) {
                    $properties = [];

                    /** @var Property|PropertyRead|PropertyWrite $docProperty */
                    foreach ($docProperties as $docProperty) {
                        /** @var Type $type */
                        $type = $docProperty->getType();
                        $properties[$docProperty->getVariableName()] = (string)$type;
                    }

                    $items[] = $properties;

                }
            }

            $class = $class->getParentClass();
        }

        $summary = [];
        foreach (array_reverse($items) as $properties) {
            foreach ($properties as $property => $type) {
                $summary[$property] = $type;
            }
        }

        $result = [];
        foreach ($summary as $property => $type) {
            $result[] = new TProperty([
                'name' => $property,
                'type' => $type
            ]);
        }

        if (isset($rest) && !empty($rest)) {
            foreach ($rest as $property => $type) {
                $result[] = new TProperty([
                    'name' => $property,
                    'type' => $type
                ]);
            }
        }

        return $result;
    }

    ///
    /// Helpers
    ///

    /**
     * @param string $name
     * @return ActiveRecord|string
     */
    protected function class(string $name)
    {
        return $this->search
            ? $this->project . '\\models\\search\\' . $name
            : $this->project . '\\models\\' . $name;
    }

    /**
     * @param string $name
     * @return ActiveRecord
     */
    protected function model(string $name)
    {
        $class = $this->class($name);
        return new $class();
    }

    public function getPropertyType(\ReflectionProperty $property)
    {
        $doc = $property->getDocComment();

        preg_match_all('#@(.*?)\n#s', $doc, $annotations);
        if (isset($annotations[1])) {
            foreach ($annotations[1] as $annotation) {
                preg_match_all('#\s*(.*?)\s+#s', $annotation, $parts);
                if (!isset($parts[1])) {
                    continue;
                }
                $declaration = $parts[1];
                if (isset($declaration[0]) && $declaration[0] === 'var') {
                    if (isset($declaration[1])) {
                        if (substr($declaration[1], 0, 1) === '$') {
                            return null;
                        } else {
                            return $declaration[1];
                        }
                    }
                }
            }
            return null;
        }

        return $doc;
    }


}

class TProperty extends BaseObject
{
    public $name;
    public $type;
    public $readonly = false;

    const MAP = [
        '[]' => [
            'array'
        ],
        'string' => [
            'string'
        ],
        'number' => [
            'int',
            'double',
            'float',
            'integer'
        ],
        'boolean' => [
            'bool',
            'boolean'
        ],
        'null' => [
            'null'
        ],
    ];

    public function ts(): string
    {
        $result = [];
        foreach (explode('|', $this->type) as $type) {
            $result[] = static::tsType($type);
        }
        return $this->name . ': ' . join(' | ', $result);
    }

    protected function tsType($type): string
    {
        if (!$type) {
            return 'any';
        }

        $array = false;

        if (StringHelper::endsWith($type, '[]')) {
            $type = substr($type, 0, -2);
            $array = true;
        }

        if (StringHelper::startsWith($type, '\\')) {
            $type = 'I' . substr($type, 1);
            return $type . ($array ? '[]' : '');
        }


        foreach (static::MAP as $section => $types) {

            if (in_array($type, $types)) {
                return $section . ($array ? '[]' : '');
            }
        }

        throw new Exception('Wrong type \'' . $type . '\'.');
    }
}