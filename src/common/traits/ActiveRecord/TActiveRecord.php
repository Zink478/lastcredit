<?php

namespace yii2custom\common\traits\ActiveRecord;

use himiklab\sortablegrid\SortableGridBehavior;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\SluggableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\helpers\Inflector;
use yii\web\ForbiddenHttpException;
use yii2custom\common\behaviors\FileBehavior;
use yii2custom\common\behaviors\LogBehavior;
use yii2custom\common\behaviors\SaveBehavior;
use yii2custom\common\core\ActiveQuery;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\exceptions\DevException;
use yii2custom\common\helpers\ModelHelper;
use yii2custom\common\helpers\RuleHelper;

/**
 * Trait ActiveRecord
 * Reserved attribute names: priority, created_at, updated_at, created_by, updated_by, slug
 *
 * @mixin ActiveRecord
 */
trait TActiveRecord
{
    ///
    /// Redeclare
    ///

    /**
     * list of fields allowed only to read
     *
     * @return array
     */
    public function readonly()
    {
        return static::primaryKey();
    }

    /**
     * list of fields allowed only to write
     *
     * @return array
     */
    public function writeonly()
    {
        return [];
    }

    ///
    /// Inherits
    ///

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        if (!$this->scenario) {
            $this->scenario = ActiveRecord::SCENARIO_DEFAULT;
        }

        if (!static::isSearchModel()) {
            $this->loadDefaultValues();
        }
    }

    public function loadDefaultValues($skipIfSet = true)
    {
        foreach (static::getTableSchema()->columns as $column) {
            if ($this->hasAttribute($column->name)) {
                if ($column->defaultValue !== null && (!$skipIfSet || $this->{$column->name} === null)) {
                    if (!is_string($column->defaultValue) || !str_contains($column->defaultValue, '(')) {
                        $this->{$column->name} = $column->defaultValue;
                    }
                }
            }
        }

        foreach ($this->rules() as $rule) {
            if ($rule[1] == 'default') {
                foreach ((array)$rule[0] as $attribute) {
                    if ($this->hasAttribute($attribute) && is_null($this->getAttribute($attribute))) {
                        $this->setAttribute($attribute, $rule['value']);
                    }
                }
            }
        }

        return $this;
    }

    public function relations()
    {
        return [];
    }

    public function extraFields()
    {
        return $this->relations();
    }

    /**
     * Auto assign behaviours
     * by reserved attributes: priority, created_at, updated_at, created_by, updated_by
     *
     * @inheritDoc
     */
    public function behaviors()
    {
        $behaviours = parent::behaviors();

        $behaviours['log'] = [
            'class' => LogBehavior::class,
            'pass' => ['log', 'stat']
        ];

        $behaviours['save'] = [
            'class' => SaveBehavior::class,
            'relations' => $this->relations()
        ];

        if ($this->hasAttribute('priority') && class_exists('himiklab\sortablegrid\SortableGridBehavior')) {
            $behaviours['sort'] = [
                'class' => 'himiklab\sortablegrid\SortableGridBehavior',
                'sortableAttribute' => 'priority'
            ];
        }

        if ($this->hasAttribute('slug') && !static::isSearchModel()) {
            $behaviours['slug'] = [
                'class' => SluggableBehavior::class,
                'attribute' => (self::i18nenabled() && in_array('title', $this->i18n())) ? 'title_' . Yii::$app->languages->default() : 'title',
                'ensureUnique' => true,
                'immutable' => true
            ];
        }

        if ($this->hasAttribute('created_at') || $this->hasAttribute('updated_at')) {
            $behaviours['timestamp'] = [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => $this->hasAttribute('created_at') ? 'created_at' : false,
                'updatedAtAttribute' => $this->hasAttribute('updated_at') ? 'updated_at' : false
            ];
        }

        if ($this->hasAttribute('created_by') || $this->hasAttribute('updated_by')) {
            /** @var ActiveQuery $relation */
            if (Yii::$app->hasProperty('user')) {
                $relation = $this->getRelation('createdBy', false) ?? $this->getRelation('updatedBy', false);
                if (!$relation || ((Yii::$app->user->identity ?? null) instanceof $relation->modelClass)) {
                    $behaviours['blame'] = [
                        'class' => BlameableBehavior::class,
                        'createdByAttribute' => $this->hasAttribute('created_by') ? 'created_by' : false,
                        'updatedByAttribute' => $this->hasAttribute('updated_by') ? 'updated_by' : false,
                    ];
                }
            }
        }

        if (
            $this->hasProperty('image') ||
            $this->hasProperty('video') ||
            $this->hasProperty('images')
        ) {
            if ($this->hasProperty('image')) {
                $attribute = 'image';
            } else if ($this->hasProperty('video')) {
                $attribute = 'video';
            } else if ($this->hasProperty('images')) {
                $attribute = 'images';
            }

            $nameAttribute = 'slug';
            if (!$this->hasProperty('slug')) {
                $pk = $this->primaryKey();
                if (count($pk) > 1) {
                    throw new DevException('Composite primary keys are not supported.');
                }
                $nameAttribute = $pk[0];
            }

            $behaviours['file'] = [
                'class' => FileBehavior::class,
                'attribute' => $attribute,
                'nameAttribute' => $nameAttribute,
                'path' => Inflector::camel2id(cl(static::modelClass()))
            ];
        }

        return $behaviours;
    }

    /**
     * @inheritDoc
     */
    public function transactions()
    {
        return array_fill_keys(array_keys($this->scenarios()), self::OP_ALL);
    }

    /**
     * @inheritDoc
     */
    public function setScenario($value)
    {
        parent::setScenario($value);
        foreach ($this->relatedRecords as $record) {
            $records = is_array($record) ? $record : [$record];
            foreach ($records as $item) {
                $item && $item->{'scenario'} = $value;
            }
        }
    }

    public function hasMany($class, $link)
    {
        return parent::hasMany(lc($class), $link);
    }

    public function hasOne($class, $link)
    {
        return parent::hasOne(lc($class), $link);
    }

    ///
    /// Find
    ///

    /**
     * Use default order
     * Always assign i18n translations
     *
     * @inheritDoc
     */
    public static function find()
    {
        /** @var ActiveQuery $query */
        $query = Yii::createObject(ActiveQuery::class, [get_called_class(), ['defaultOrder' => static::order()]]);
        if (!static::isSearchModel() && self::i18nenabled()) {
            $query->with('i18nModelMessages');
        }

        return $query;
    }

    /**
     * @inheritDoc
     *
     * @param $condition
     * @param array|string|null $with
     * @return static
     */
    public static function findOne($condition, $with = []): ?self
    {
        if (is_array($condition)) {
            $condition = array_filter($condition);
        }

        if ($condition) {
            $query = static::{'findByCondition'}($condition);
            if ($with) {
                $query->with($with);
            }
            return $query->one();
        }

        return null;
    }

    /**
     * @inheritDoc
     *
     * @param $condition
     * @return static[]
     */
    public static function findAll($condition = null)
    {
        if ($condition) {
            $result = parent::findAll($condition);
        } else {
            $result = static::find()->all();
        }

        return $result;
    }

    public static function get(array $data, bool $must = true)
    {
        return static::findOne($data) ?? static::create($data, $must);
    }

    public static function create(array $data, bool $must = true)
    {
        $model = new static(array_diff_key($data, array_flip(static::primaryKey())));
        $must ? $model->mustInsert() : $model->insert();
        return $model;
    }

    ///
    /// Fields
    ///

    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $fields = $fields ? array_intersect($this->readable(false), $fields) : $this->readable(false);
        return parent::toArray($fields, $expand, $recursive);
    }

    /**
     * @return string[]
     */
    public function columns($schema = false)
    {
        $result = ModelHelper::combine($this->fields(), $this->readonly($schema), $this->writeonly($schema));
        $result = array_intersect($result, RuleHelper::attributes($this->rules()));

        if (!$schema) {
            $result = ModelHelper::combine($result, $this->extraFields());
        }

        return $schema ? array_intersect(static::getTableSchema()->columnNames, $result) : $result;
    }

    /**
     * List of readable fields
     *
     * @return array
     */
    public final function readable($schema = true)
    {
        $fields = ModelHelper::combine($this->fields(), $this->readonly(), !$schema ? $this->extraFields() : []);
        $fields = array_diff($fields, $this->writeonly());
        $fields = $schema ? array_intersect($fields, static::getTableSchema()->columnNames) : $fields;

        if (!$schema) {
            $relations = $this->getBehavior('save')->{'relations'} ?? [];
            foreach ($relations as $key => &$relation) {
                if (!is_string($relation)) {
                    $relation = $key;
                }
            }
            $fields = array_merge($fields, $relations);
        }

        return array_combine($fields, $fields);
    }

    /**
     * list of fields allowed to update
     *
     * @param bool $schema
     * @return array
     */
    public final function writable($schema = false)
    {
        $fields = ModelHelper::combine($this->fields(), $this->writeonly(), !$schema ? $this->extraFields() : []);
        $fields = array_diff($fields, $this->readonly());
        $fields = $schema ? array_intersect($fields, static::getTableSchema()->columnNames) : $fields;

        if (!$schema) {
            $relations = $this->getBehavior('save')->{'relations'} ?? [];
            foreach ($relations as $key => &$relation) {
                if (!is_string($relation)) {
                    $relation = $key;
                }
            }
            $fields = array_merge($fields, $relations);
        }

        return array_combine($fields, $fields);
    }

    ///
    /// Access
    ///

    /**
     * @inheritDoc
     */
    public function beforeSave($insert)
    {
        if (!$this->allow(self::OP_INSERT) && !$this->allow(self::OP_UPDATE)) {
            throw new ForbiddenHttpException('Saving for model "' . static::class . '" is not allowed.');
        }

        if (!$this->allow(self::OP_INSERT)) {
            if ($insert) {
                throw new ForbiddenHttpException('Inserting to model "' . static::class . '" is not allowed.');
            }
        }

        return parent::beforeSave($insert);
    }

    /**
     * @inheritDoc
     */
    public function beforeDelete()
    {
        if (!$this->allow(self::OP_DELETE)) {
            throw new ForbiddenHttpException('Deleting for model "' . static::class . '" is not allowed.');
        }

        return parent::beforeDelete();
    }

    /**
     * @param int|null $op
     * @return bool
     */
    public function allow($op = null)
    {
        switch ($op) {
            case self::OP_INSERT:
                return $this->allowInsert();
            case self::OP_UPDATE:
                return $this->allowUpdate();
            case self::OP_DELETE:
                return $this->allowDelete();
            case self::OP_ALL:
                return $this->allowInsert()
                    && $this->allowUpdate()
                    && $this->allowDelete();
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    public function allowInsert()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function allowUpdate()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function allowDelete()
    {
        return true;
    }

    ///
    /// Static
    ///

    /**
     * Model attribute used as title
     *
     * @return string
     */
    public static function titleAttribute()
    {
        return 'title';
    }

    /**
     * Last value of
     *
     * @param string $attribute
     * @return int
     */
    public static function last(string $attribute = 'updated_at'): int
    {
        static $max = null;
        if (is_null($max)) {
            $max = static::find()->select(new Expression('max(' . $attribute . ')'))->scalar() ?? 0;
        }
        return $max;
    }

    /**
     * @param callable|array|string|null $title
     * @param string|null $key
     * @param callable|array|null $conditions
     * @return array
     */
    public static function list($title = null, string $key = null, $conditions = null)
    {
        if ($title && !is_string($title)) {
            $conditions = $title;
            $title = null;
        }

        $key = $key ?? 'id';
        $nested = strpos($title, '.') !== false;
        $title = $title ?? static::titleAttribute();
        $query = static::find()->indexBy($key);

        if (!$nested) {
            $query->select($title);
            $query->orderBy([$title => SORT_ASC]);
        }

        if ($conditions) {
            if (is_callable($conditions)) {
                $conditions($query);
            } else {
                if (is_int($conditions)) {
                    $query->andWhere(['!=', $key, $conditions]);
                } else {
                    $query->andWhere($conditions);
                }
            }
        }

        if (!$nested) {
            return $query->column();
        } else {
            $result = [];
            /** @var self $model */
            foreach ($query->all() as $id => $model) {
                $current = $model;
                $e = explode('.', $title);
                $l = count($e) - 1;
                foreach ($e as $i => $part) {
                    if ($i == $l) {
                        $result[$id] = $current->{$part};
                        break;
                    }
                    $current = $current->{$part};
                }
            }
        }

        return $result;
    }

    /**
     * @param null $conditions
     * @param string|array $key
     * @param string|array $title
     * @return array
     */
    public static function dict($conditions = null, $key = 'id', $title = 'title')
    {
        if (!is_array($key)) {
            $key = [$key => $key];
        }

        if (!is_array($title)) {
            $title = [$title => $title];
        }

        $result = [];
        foreach (static::list(key($title), key($key), $conditions) as $k => $t) {
            $result[] = [
                current($key) => $k,
                current($title) => $t,
            ];
        }

        return $result;
    }

    /**
     * Default order by condition, used if not set manually
     *
     * @return array|null
     */
    public static function order()
    {
        $table = static::tableName();
        if ($table == 'i18n_model_message') {
            // TODO fix using i18n tables when multilang disabled
            return [];
        }

        if (in_array('priority', array_keys(static::getTableSchema()->columns))) {
            return ['priority' => SORT_ASC];
        }

        $order = [];
        foreach (static::primaryKey() as $attribute) {
            $order[$attribute] = SORT_DESC;
        }

        return $order;
    }

    ///
    /// Ident
    ///

    public static function isSearchModel($class = null)
    {
        return str_ends_with($class ?? static::class, 'Search');
    }

    public static function searchClass($class = null)
    {
        return ns($class ?? static::class, 'search') . '\\search\\' . cl($class ?? static::class, 'Search') . 'Search';
    }

    public static function modelClass($class = null)
    {
        return ns($class ?? static::class, 'search') . '\\' . cl($class ?? static::class, 'Search');
    }

    /**
     * @deprecated
     */
    public static function modelName()
    {
        return cl(static::modelClass());
    }

    ///
    /// Helpers
    ///

    private static function i18nenabled()
    {
        return Yii::$app->has('languages')
            && Yii::$app->languages->enabled()
            && static::i18n();
    }
}
