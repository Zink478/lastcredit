<?php

namespace yii2custom\common\core\generators\model;

use Yii;
use yii\base\NotSupportedException;
use yii\db\pgsql\Schema;
use yii\gii\CodeFile;
use yii\helpers\Inflector;

class Generator extends \yii\gii\generators\model\Generator
{
    public $exclude = [];
    public $generateRelations = self::RELATIONS_ALL_INVERSE;

    public function rules()
    {
        return array_merge(parent::rules(), [
            [['exclude'], 'safe']
        ]);
    }

    public function beforeValidate()
    {
        if (!str_ends_with($this->tableName, '*') && !$this->modelClass) {
            $this->modelClass = Inflector::camelize($this->tableName);
            $this->exclude = [];
        } else {
            $this->exclude = array_filter(explode(',', $this->exclude));
        }

        return parent::beforeValidate();
    }

    /**
     * {@inheritdoc}
     */
    public function generate()
    {
        $result = [];

        if ($this->tableName == '*') {
            foreach ($this->tableNames as $table) {
                $this->tableName = $table;
                $this->tableNames = [$table];
                $this->modelClass = $this->generateClassName($this->tableName);
                $result = array_merge($result, $this->generate());
            }

            return $result;
        }

        $files = parent::generate();

        foreach ($files as $file) {
            $path = $file->path;
            $class = substr(basename($file->path), 0, -strlen('.php'));
            $table = Inflector::underscore($class);

            if ($this->singularize) {
                $info = pathinfo($path);
                $path = $info['dirname'] . '/' . Inflector::singularize($info['filename']) . '.' . $info['extension'];
                $class = Inflector::singularize($class);
                $this->modelClass = Inflector::singularize($this->modelClass);
            }

            $baseClass = '/Base' . $class;
            $basePath = dirname($file->path) . '/base' . $baseClass . '.php';
            $file->path = $basePath;

            if (!file_exists($path)) {
                $result[] = new CodeFile(
                    Yii::getAlias($path),
                    $this->render('extend.php', [
                        'tableName' => $table,
                        'className' => $class,
                    ])
                );
            }

            $result[] = $file;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function generateClassName($tableName, $useSchemaName = null)
    {
        $schema = null;
        if ($this->pg() && strpos($tableName, '.')) {
            $useSchemaName = true;
        }

        $result = parent::generateClassName($tableName, $useSchemaName);

        if ($useSchemaName) {
            $result = $this->changeClassName($result, $tableName);
        }

        return $result;
    }

    /**
     * Remove schema name from relation, if tables placed at same schema
     *
     * @inheritDoc
     */
    protected function generateRelationName($relations, $table, $key, $multiple, $dev = false)
    {
        $result = parent::generateRelationName($relations, $table, $key, $multiple, $dev);
        $class = $this->changeClassName(Inflector::camelize($table->fullName), $table->fullName);

        if (str_starts_with($result, $class) && ctype_upper(substr($result, strlen($class), 1))) {
            $result = substr($result, strlen($class));
        }

        return $result;
    }

    public function generateLabels($table)
    {
        $result = parent::generateLabels($table);
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function generateRules($table)
    {
        $types = [];
        $lengths = [];
        $arrays = [];

        foreach ($table->columns as $column) {

            if ($column->autoIncrement) {
                continue;
            }

            if (!$column->allowNull && $column->defaultValue === null) {
                if (!in_array($column->name, ['created_at', 'updated_at', 'created_by', 'updated_by', 'priority'])) { // CHANGE
                    $types['required'][] = $column->name;
                }
            }

            switch ($column->type) {
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                case Schema::TYPE_TINYINT:
                    $types['integer'][] = $column->name;
                    break;
                case Schema::TYPE_BOOLEAN:
                    $types['boolean'][] = $column->name;
                    break;
                case Schema::TYPE_FLOAT:
                case Schema::TYPE_DOUBLE:
                case Schema::TYPE_DECIMAL:
                case Schema::TYPE_MONEY:
                    $types['number'][] = $column->name;
                    break;
                case Schema::TYPE_DATE: // CHANGE
                    $types['date'][] = $column->name;
                    break;
                case Schema::TYPE_TIME:
                case Schema::TYPE_DATETIME:
                case Schema::TYPE_TIMESTAMP:
                case Schema::TYPE_JSON:
                    $types['safe'][] = $column->name;
                    break;
                default: // strings
                    if ($column->size > 0) {
                        $lengths[$column->size][] = $column->name;
                    } else {
                        $types['string'][] = $column->name;
                    }
            }

            if ($this->pg() && $column->dimension) {
                $arrays[] = $column->name;
            }
        }

        foreach ($types as $type => $columns) {
            $single = array_diff($columns, $arrays);
            $multiple = array_intersect($columns, $arrays);

            if ($type == 'date') {
                if ($single) {
                    $rules[] = "[['" . implode("', '", $single) . "'], '$type', 'format' => 'php:Y-m-d']";
                }
                if ($multiple) {
                    $rules[] = "[['" . implode("', '", $multiple) . "'], 'each', 'rule' => ['$type', 'format' => 'php:Y-m-d']]";
                }
            } else {
                if ($single) {
                    $rules[] = "[['" . implode("', '", $single) . "'], '$type']";
                }
                if ($multiple) {
                    $rules[] = "[['" . implode("', '", $multiple) . "'], 'each', 'rule' => ['$type']]";
                }
            }
        }

        foreach ($lengths as $length => $columns) {
            $single = array_diff($columns, $arrays);
            $multiple = array_intersect($columns, $arrays);

            if ($single) {
                $rules[] = "[['" . implode("', '", $single) . "'], 'string', 'max' => $length]";
            }
            if ($multiple) {
                $rules[] = "[['" . implode("', '", $multiple) . "'], 'each', 'rule' => ['string', 'max' => $length]]";
            }
        }

        $db = $this->getDbConnection();

        // Unique indexes rules
        try {
            $uniqueIndexes = array_merge($db->getSchema()->findUniqueIndexes($table), [$table->primaryKey]);
            $uniqueIndexes = array_unique($uniqueIndexes, SORT_REGULAR);
            foreach ($uniqueIndexes as $uniqueColumns) {
                // Avoid validating auto incremental columns
                if (!$this->isColumnAutoIncremental($table, $uniqueColumns)) {
                    $attributesCount = count($uniqueColumns);

                    if ($attributesCount === 1) {
                        $rules[] = "[['" . $uniqueColumns[0] . "'], 'unique']";
                    } elseif ($attributesCount > 1) {
                        $columnsList = implode("', '", $uniqueColumns);
                        $rules[] = "[['$columnsList'], 'unique', 'targetAttribute' => ['$columnsList']]";
                    }
                }
            }
        } catch (NotSupportedException $e) {
            // doesn't support unique indexes information...do nothing
        }

        // Exist rules for foreign keys
        foreach ($table->foreignKeys as $refs) {
            $refTable = $refs[0];
            $refTableSchema = $db->getTableSchema($refTable);
            if ($refTableSchema === null) {
                // Foreign key could point to non-existing table: https://github.com/yiisoft/yii2-gii/issues/34
                continue;
            }
            $refClassName = $this->generateClassName($refTable);
            unset($refs[0]);
            $attributes = implode("', '", array_keys($refs));
            $targetAttributes = [];
            foreach ($refs as $key => $value) {
                $targetAttributes[] = "'$key' => '$value'";
            }
            $targetAttributes = implode(', ', $targetAttributes);
            $rules[] = "[['$attributes'], 'exist', 'skipOnError' => true, 'targetClass' => $refClassName::class, 'targetAttribute' => [$targetAttributes]]";
        }

        return $rules;
    }

    /**
     * @inheritDoc
     */
    protected function generateProperties($table)
    {
        $properties = parent::generateProperties($table);

        if ($this->pg()) {
            // process array columns
            foreach ($properties as $name => $property) {
                $column = $table->columns[$name];
                if ($column->dimension) {
                    $properties[$name]['type'] = preg_replace('/\|null$/', '', $property['type']);
                    $properties[$name]['type'] .= '[]';
                    if ($column->allowNull) {
                        $properties[$name]['type'] .= '|null';
                    }
                }
            }
        }

        return $properties;
    }

    /**
     * @inheritDoc
     */
    protected function generateRelations($ignoreInherits = false)
    {
        $result = parent::generateRelations();


        foreach ($result as $table => &$relations) {
            if ($table == $this->tableName) {
                foreach ($relations as $name => $relation) {

                    if (str_ends_with($name, 'Rels')) {
                        unset($relations[$name]);
                        continue;
                    }

                    if ( // Replace hasMany to hasOne on via single relations
                        $this->pg() && $relation[2] && // is multiple
                        strpos($relation[0], ')->viaTable(')
                        && strpos($relation[0], '->hasMany(')
                    ) {
                        preg_match('/viaTable\(\'([\w_]+)\', /', $relation[0], $ms);
                        [, $table] = $ms;

                        $foreign = $relations[$name];
                        preg_match("/, \['([\w\d_]+)' => '[\w\d_]+'\]\);/", $foreign[0], $ms);
                        [, $lk] = $ms;

                        if ($this->isUnique($table, $lk)) {
                            $rels = [];
                            $relation[0] = str_replace('->hasMany(', '->hasOne(', $relation[0]);
                            $relation[2] = false;

                            foreach ($relations as $n => $r) {
                                if ($n == $name) {
                                    $rels[$relation[1]] = $relation;
                                } else {
                                    $rels[$n] = $r;
                                };
                            }

                            $relations = $rels;
                        }

                        unset($relations[Inflector::camelize($table)]);
                    }
                }
            }
        }

        return $result;
    }

    ///
    /// Helpers
    ///

    protected function baseModel()
    {
        /* @var $baseModel \yii\db\ActiveRecord */
        static $baseModel;

        if ($baseModel === null) {
            $baseClass = $this->baseClass;
            $baseClassReflector = new \ReflectionClass($baseClass);
            if ($baseClassReflector->isAbstract()) {
                $baseClassWrapper =
                    'namespace ' . __NAMESPACE__ . ';' .
                    'class GiiBaseClassWrapper extends \\' . $baseClass . ' {' .
                    'public static function tableName(){' .
                    'return "' . addslashes($table->fullName) . '";' .
                    '}' .
                    '};' .
                    'return new GiiBaseClassWrapper();';
                $baseModel = eval($baseClassWrapper);
            } else {
                $baseModel = new $baseClass();
            }
            $baseModel->setAttributes([]);
        }

        return $baseModel;
    }

    /**
     * @return \yii\db\TableSchema|null
     */
    protected function baseTableSchema()
    {
        $baseClass = $this->baseClass;
        $baseTable = $baseClass::tableName();
        return $baseTableSchema = $this->getDbConnection()->getTableSchema($baseTable);
    }

    private function isUnique(string $table, string $column): bool
    {
        $schema = 'public';
        $parts = explode('.', $table);
        if (count($parts) > 1) {
            $schema = $parts[0];
            $table = $parts[1];
        }

        $sql = "SELECT t.constraint_name FROM information_schema.table_constraints AS t
        INNER JOIN information_schema.constraint_column_usage AS c ON t.constraint_name = c.constraint_name AND c.constraint_schema = t.table_schema
        WHERE t.constraint_type IN ('UNIQUE') AND  t.table_schema = '$schema' AND  t.table_name = '$table' AND c.column_name = '$column'";
        $name = $this->getDbConnection()->createCommand($sql)->queryScalar();

        if ($name) {
            $sql = "SELECT COUNT(*) FROM information_schema.table_constraints AS t
            INNER JOIN information_schema.constraint_column_usage AS c ON t.constraint_name = c.constraint_name AND c.constraint_schema = t.table_schema WHERE t.constraint_name = '$name'";
            $count = $this->getDbConnection()->createCommand($sql)->queryScalar();

            return $count < 2;
        }

        return false;
    }

    /**
     * Remove diplicate, when table name starts with name of schema
     *
     * @param string $class
     * @param string $table
     * @return string
     */
    protected function changeClassName(string $class, string $table)
    {
        if ($this->pg() && strpos($table, '.')) {
            [$schema] = explode('.', $table);
            if (str_starts_with($class, Inflector::camelize($schema) . Inflector::camelize($schema))) {
                $class = substr($class, strlen($schema));
            }
        }

        return $class;
    }

    protected function getTableNames()
    {
        $tableNames = [];

        if ($this->pg() && $this->tableName == '*') {
            // Table list from all schemas
            $sql = 'SELECT "schema_name" FROM "information_schema"."schemata"
                    WHERE "schema_name" != \'information_schema\' AND "schema_name" NOT LIKE \'pg_%\'';

            $tableName = $this->tableName;
            $schemas = $this->getDbConnection()->createCommand($sql)->queryColumn();
            foreach ($schemas as $schema) {
                $this->tableNames = null;
                $this->tableName = $schema == 'public' ? '*' : $schema . '.*';
                $tableNames = array_merge($tableNames, parent::getTableNames());
            }
            $this->tableName = $tableName;
        } else {
            $tableNames = parent::getTableNames();
        }

        foreach ($tableNames as $key => $tableName) {
            if (str_ends_with($tableName, '_rel')) {
                // Igrnore relation tables, used only at viaTable() calls
                unset($tableNames[$key]);
            } else {
                // Ignore tables corresponding exclude patterns
                foreach ($this->exclude as $pattern) {
                    if (
                        $pattern == $tableName || (
                            (str_ends_with($pattern, '_*') || str_ends_with($pattern, '.*')) &&
                            str_starts_with($tableName, substr($pattern, 0, -2))
                        )
                    ) {
                        unset($tableNames[$key]);
                    }
                }
            }
        }

        return $this->tableNames = array_diff($tableNames, ['migration']);
    }

    ///
    /// Private
    ///

    private function pg(): bool
    {
        return $this->getDbDriverName() === 'pgsql';
    }

    private static function diff($array1, $array2)
    {
        $result = [];

        if (is_array($array1)) {
            foreach ($array1 as $key => $value) {
                if (!array_key_exists($key, $array2) || self::diff($value, $array2[$key])) {
                    $result[$key] = $value;
                }
            }
        } else {
            return false;
        }

        return $result;
    }
}