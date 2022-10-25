<?php

namespace yii2custom\common\core\generators\api;

use Yii;
use yii\db\Connection;
use yii\gii\CodeFile;
use yii\helpers\Inflector;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\helpers\ModelHelper;
use yii2custom\common\helpers\RuleHelper;

class Generator extends \yii\gii\generators\crud\Generator
{
    const RESERVED_ATTRIBUTES = ['priority', 'image_id', ...self::ATBY_ATTRIBUTES];
    const ATBY_ATTRIBUTES = ['created_at', 'updated_at', 'created_by', 'updated_by'];

    public $generateSchema = true;
    public $baseNs = 'common\models';
    public $apiModelClass;
    public $section = 'api';
    public $tableName = '*';
    public $db = 'db';

    protected $tableNames = null;

    public function beforeValidate()
    {
        if ($this->tableName != '*') {
            $this->reset($this->tableName);
        }

        return parent::beforeValidate();
    }

    protected function reset($table)
    {
        $this->tableName = $table;
        $name = $this->generateClassName($table);
        $this->modelClass = $this->section . '\\models\\' . $name;
        $this->searchModelClass = $this->section . '\\models\\search\\' . $name . 'Search';
        $this->controllerClass = $this->section . '\\controllers\\' . $name . 'Controller';
        $this->baseControllerClass = $this->section . '\\core\\Controller';
    }

    public function rules()
    {
        $rules = parent::rules();
        RuleHelper::remove($rules, 'modelClass', 'validateClass');
        RuleHelper::remove($rules, 'modelClass', 'validateModelClass');
        RuleHelper::remove($rules, 'modelClass', 'required');
        RuleHelper::remove($rules, 'controllerClass', 'required');

        return array_merge($rules, [
            [['modelClass'], 'validateNewClass'],
            [['controllerClass', 'modelClass'], 'required', 'when' => function ($model) {
                return $model->tableName != '*';
            }],
        ]);
    }

    /**
     * Move *_at, *_date aolumns to safe type
     * @return array
     */
    public function generateSearchRules()
    {
        $rules = [];
        foreach (parent::generateSearchRules() as $line) {
            preg_match('/^\[\[(.+?)\], \'(.+?)\']$/', $line, $ms);
            $types[$ms[2]] = explode("', '", trim($ms[1], '\''));
        }

        foreach ($types as $type => &$attributes) {
            foreach ($attributes as $key => $attribute) {
                if (str_ends_with($attribute, '_at')) {
                    $types['safe'][] = $attribute;
                    unset($attributes[$key]);
                }
            }

            $this->sort($attributes);
        }

        foreach ($types as $type => $columns) {
            $rules[] = "[['" . implode("', '", $columns) . "'], '$type']";
        }

        return $rules;
    }

    /**
     * Add *_at date conditions
     * @return array|void
     */
    public function generateSearchConditions()
    {
        $conditions = [];

        // Parse

        foreach (parent::generateSearchConditions() as $condition) {
            preg_match_all('/\$this->(.+)(,|\])/', $condition, $ms);
            $type = strpos($condition, "'ilike'") ? 'like' : 'hash';
            $conditions[$type] = $ms[1];
        }

        // Cahnge

        foreach ($conditions as $type => &$attributes) {
            foreach ($attributes as $key => $attribute) {
                if (str_ends_with($attribute, '_at') || str_ends_with($attribute, '_date')) {
                    $conditions['date'][] = $attribute;
                    unset($attributes[$key]);
                }
            }

            $this->sort($attributes);
        }

        $result = [];

        // Stringify

        $hashConditions = [];
        foreach ($conditions['hash'] ?? [] as $column) {
            $hashConditions[] = "'{$column}' => \$this->{$column},";
        }

        $dateConditions = [];
        foreach ($conditions['date'] ?? [] as $column) {
            $dateConditions[] = "'{$column}' => \$this->{$column},";
        }

        $likeConditions = [];
        $likeKeyword = $this->getClassDbDriverName() === 'pgsql' ? 'ilike' : 'like';
        foreach ($conditions['like'] ?? [] as $column) {
            $likeConditions[] = "->andFilterWhere(['{$likeKeyword}', '{$column}', \$this->{$column}])";
        }

        if (!empty($hashConditions)) {
            $result[] = "\$query->andFilterWhere([\n"
                . str_repeat(' ', 12) . implode("\n" . str_repeat(' ', 12), $hashConditions)
                . "\n" . str_repeat(' ', 8) . "]);\n";
        }

        if (!empty($dateConditions)) {
            $result[] = "\$query->andDateFilterWhere([\n"
                . str_repeat(' ', 12) . implode("\n" . str_repeat(' ', 12), $dateConditions)
                . "\n" . str_repeat(' ', 8) . "]);\n";
        }

        if (!empty($likeConditions)) {
            $result[] = "\$query" . implode("\n" . str_repeat(' ', 12), $likeConditions) . ";\n";
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function generate()
    {
        $files = [];

        if ($this->tableName == '*') {
            foreach ($this->getTableNames() as $tableName) {
                $this->reset($tableName);
                $files = array_merge($files, $this->generate());
            }

            return $files;
        }

        eval ("namespace " . ns($this->modelClass) . ";
            class " . cl($this->modelClass) . " extends \\" . ($this->baseNs . '\\' . cl($this->modelClass)) . " {}
        ");

        /** @var ActiveRecord $model */
        $model = new $this->modelClass();
        $fields = array_keys(ModelHelper::combine($model->fields()));

        foreach ($fields as &$field) {
            $field = '\'' . $field . '\'';
        }

        $modelFile = Yii::getAlias('@' . str_replace('\\', '/', ltrim($this->modelClass, '\\') . '.php'));
        if (!file_exists($modelFile)) {
            $files[] = new CodeFile($modelFile, $this->render('model.php', ['fields' => $fields]));
        }

        $controllerFile = Yii::getAlias('@' . str_replace('\\', '/', ltrim($this->controllerClass, '\\')) . '.php');
        if (!file_exists($controllerFile)) {
            $files[] = new CodeFile($controllerFile, $this->render('controller.php'));
        }

        if (!empty($this->searchModelClass)) {
            $searchModel = Yii::getAlias('@' . str_replace('\\', '/', ltrim($this->searchModelClass, '\\') . '.php'));
            $files[] = new CodeFile($searchModel, $this->render('search.php'));
        }

        return $files;
    }

    protected function getTableNames()
    {
        if ($this->tableNames !== null) {
            return $this->tableNames;
        }
        $db = Yii::$app->db;
        if ($db === null) {
            return [];
        }
        $tableNames = [];
        if (strpos($this->tableName, '*') !== false) {
            if (($pos = strrpos($this->tableName, '.')) !== false) {
                $schema = substr($this->tableName, 0, $pos);
                $pattern = '/^' . str_replace('*', '\w+', substr($this->tableName, $pos + 1)) . '$/';
            } else {
                $schema = '';
                $pattern = '/^' . str_replace('*', '\w+', $this->tableName) . '$/';
            }

            foreach ($db->schema->gettableNames($schema) as $table) {
                if (preg_match($pattern, $table)) {
                    $tableNames[] = $schema === '' ? $table : ($schema . '.' . $table);
                }
            }
        } elseif (($table = $db->getTableSchema($this->tableName, true)) !== null) {
            $tableNames[] = $this->tableName;
            $this->classNames[$this->tableName] = $this->modelClass;
        }

        foreach ($tableNames as $key => $tableName) {
            if (str_ends_with($tableName, '_rel')) {
                unset($tableNames[$key]);
            }
        }

        return $this->tableNames = array_diff($tableNames, ['migration']);
    }

    protected function sort(&$attributes)
    {
        $columns = $this->getColumnNames();
        usort($attributes, function ($a, $b) use ($columns) {
            $ak = (array_search($a, $columns) ?: -1);
            $bk = (array_search($b, $columns) ?: -1);
            return ($ak < $bk) ? -1 : 1;
        });
    }

    protected function generateClassName($table)
    {
        $class = Inflector::camelize($table);
        if ($this->getDbDriverName() === 'pgsql' && strpos($table, '.')) {

            [$schema] = explode('.', $table);
            if (str_starts_with($class, Inflector::camelize($schema) . Inflector::camelize($schema))) {
                $class = substr($class, strlen($schema));
            }
        }

        return $class;
    }

    protected function getDbDriverName()
    {
        $db = Yii::$app->get($this->db, false);

        return $db instanceof Connection ? $db->driverName : null;
    }
}