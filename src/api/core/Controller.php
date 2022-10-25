<?php

namespace yii2custom\api\core;

use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\db\ActiveQueryInterface;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\helpers\Inflector;
use yii\rest\ActiveController;
use yii\web\NotFoundHttpException;
use yii2custom\api\auth\CookieAuth;
use yii2custom\api\core\actions\CreateAction;
use yii2custom\api\core\actions\IndexAction;
use yii2custom\api\core\actions\UpdateAction;
use yii2custom\api\core\actions\ValidateAction;
use yii2custom\api\core\actions\ViewAction;
use yii2custom\common\core\ActiveRecord;

/**
 * @property array $extra
 */
abstract class Controller extends ActiveController
{
    public $searchModelClass;
    public $enableCsrfValidation = false;
    public $updateScenario = ActiveRecord::SCENARIO_UPDATE;
    public $createScenario = ActiveRecord::SCENARIO_CREATE;
    private $first = true;

    protected $_extra = [];

    public function getExtra(): array
    {
        return $this->_extra;
    }

    public function setExtra(array $value)
    {
        $this->_extra = $value;
    }

    ///
    /// Redeclare
    ///

    /**
     * List of available actions
     *
     * @return string[]|null
     */
    public function allow()
    {
        return null;
    }

    /**
     * @param ActiveQueryInterface $query
     * @return array
     */
    public function conditions($query)
    {
        return [];
    }

    /**
     * Custom Query object
     *
     * @return \yii2custom\common\core\ActiveQuery
     */
    public function query()
    {
        /** @var ActiveRecord $class */
        $class = $this->modelClass;
        return $class::find();
    }

    ///
    /// Inherits
    ///

    public function init()
    {
        if (!$this->searchModelClass) {
            if (!$this->modelClass) {
                $this->modelClass = lc('common\models\\' . cl($this, 'Controller'));
            }
            $this->searchModelClass = lc('common\models\search\\' . cl($this->modelClass) . 'Search');
        } else if (!$this->modelClass) {
            $this->modelClass = $section . 'common\models\\' . substr(cl($this->searchModelClass), 0, -strlen('Search'));
        }

        parent::init();
    }

    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs['create'] = ['GET', 'POST'];
        $verbs['update'] = ['GET', 'POST'];
        $verbs['validate'] = ['POST'];
        $verbs['delete'] = ['POST'];
        return $verbs;
    }

    public function actions()
    {
        $allow = null;
        if ($this->first) {
            $allow = $this->allow();
            $this->first = false;
        }

        $actions = parent::actions();

        if (!empty($actions['create'])) {
            $actions['create']['class'] = CreateAction::class;
        }
        if (!empty($actions['update'])) {
            $actions['view']['class'] = ViewAction::class;
        }
        if (!empty($actions['update'])) {
            $actions['update']['class'] = UpdateAction::class;
        }
        if (!empty($actions['index'])) {
            $actions['index']['class'] = IndexAction::class;
        }

        $actions['validate'] = [
            'class' => ValidateAction::class,
            'modelClass' => $this->modelClass,
            'checkAccess' => [$this, 'checkAccess'],
            'scenario' => $this->createScenario,
        ];

        return !is_null($allow) ? array_intersect_key($actions, array_flip($this->allow())) : $actions;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['rateLimiter']);
        $behaviors['contentNegotiator']['formats'] = ['*' => 'json'];

        try {
            $auth = (bool)Yii::$app->user->identityClass;
        } catch (\Exception $e) {
            $auth = false;
        }

        if ($auth) {
            $behaviors['auth'] = [
                'class' => CompositeAuth::class,
                'optional' => ['*'],
                'except' => ['login'],
                'authMethods' => [
                    ['class' => QueryParamAuth::class],
                    ['class' => HttpBearerAuth::class],
                    ['class' => CookieAuth::class]
                ],
            ];
        }

        return $behaviors;
    }

    protected function serializeData($data, $expand = null, $fields = null)
    {
        $fields = $fields ?? $this->actionParams['fields'] ?? Yii::$app->request->get('fields');
        $expand = $expand ?? $this->actionParams['expand'] ?? Yii::$app->request->get('expand');

        if (is_array($expand)) {
            $expand = join(',', $expand); // TODO use nested format
        }

        return Yii::createObject(Serializer::class)
            ->serialize($data, $fields, $expand);
    }

    public function bindActionParams($action, $params)
    {
        $result = parent::bindActionParams($action, $params);
        if ($params['expand'] ?? null) {
            $this->actionParams['expand'] = $params['expand'];
        }
        if ($params['fields'] ?? null) {
            $this->actionParams['fields'] = $params['fields'];
        }
        return $result;
    }

    public function beforeAction($action)
    {
        $method = 'before' . Inflector::camelize($action->id);
        if (method_exists($this, $method)) {
            $result = $this->$method();
        }
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        if (!($result instanceof Model && $result->hasErrors())) {
            $method = 'after' . Inflector::camelize($action->id);
            if (method_exists($this, $method)) {
                $result = $this->$method($result);
            }
        }

        return parent::afterAction($action, $result);
    }

    ///
    /// Helpers
    ///

    public function findModel($id)
    {
        if (!$id) {
            throw new Exception('Empty id.');
        }

        $query = $this->query();
        $this->modelClass = $query->modelClass;
        $table = $query->from ? array_key_first($query->from) : $this->modelClass::tableName();
        $conditions = is_array($id) ? $id : (is_numeric($id) ? [$table . '.id' => $id] : [$table . '.slug' => $id]);
        $model = $query
            ->andWhere($conditions)
            ->with(Serializer::normalize(Yii::$app->request->get('expand')))
            ->one();

        if ($model) {
            return $model;
        }

        throw new NotFoundHttpException("Object not found: " . (is_array($id) ? json_encode($id, true) : $id) . "");
    }

    public function getModelByPath($path)
    {
        $current = null;
        $parts = explode('.', $path);
        $count = count($parts);

        foreach ($parts as $i => $name) {
            $name = lcfirst(Inflector::camelize($name));
            if (!$current) {
                $ns = 'common\models\\';
                if (str_ends_with($path, '-form')) {
                    $ns .= 'forms\\';
                }
                $class = lc($ns . ucfirst($name));
                $current = new $class();
            } else {
                $owner = $current;
                $relation = $current->getRelation($name, false);
                if (!$relation) {
                    continue;
                }

                $model = new ($relation->modelClass)();
                $current->{$name} = $relation->multiple ? [$model] : $model;
                $current = $model;

                if ($i == ($count - 1)) {
                    $current->owner = $owner;
                }
            }
        }

        return $current;
    }
}