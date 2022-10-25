<?php

namespace yii2custom\api\core\actions;

use kcfinder\dir;
use yii\helpers\Inflector;
use yii2custom\api\core\Controller;
use yii2custom\api\core\Serializer;
use yii2custom\common\core\ActiveQuery;
use yii2custom\common\core\ActiveRecord;
use yii\base\Exception;
use yii\data\ActiveDataProvider;
use yii2custom\common\helpers\ModelHelper;
use Yii;

/**
 * @property Controller $controller
 */
class IndexAction extends \yii\rest\IndexAction
{
    /** @var array|callable */
    public $conditions = [];
    public $scenario = ActiveRecord::SCENARIO_DEFAULT;

    protected function prepareDataProvider()
    {
        /** @var ActiveRecord $searchModel */
        $searchModelClass = $this->controller->{'searchModelClass'};

        if (!$searchModelClass) {
            throw new Exception(get_class($this->controller) . "::searchModelClass must be set");
        }

        if (class_exists($searchModelClass)) {
            $searchModel = new $searchModelClass(['scenario' => $this->scenario]);
            $params = Yii::$app->request->queryParams['params'] ?? Yii::$app->request->queryParams;
            if ($searchModel->formName()) {
                $params = [$searchModel->formName() => $params];
            }

            if ($this->prepareDataProvider !== null) {
                call_user_func($this->prepareDataProvider, $this, $params);
            }

            /** @var ActiveDataProvider $provider */
            $provider = $searchModel->{'search'}($params, $this->controller->query());

            if (!$searchModel->hasErrors()) {
                $modelClass = $provider->query->modelClass;
                $model = new $modelClass();
                $table = $provider->query->from ? array_key_first($provider->query->from) : $searchModel::tableName();
                $provider->query->select(ModelHelper::columns($table, $model->readable()));
                $provider->query->with(Serializer::normalize(Yii::$app->request->get('expand', [])));
                $conditions = $this->conditions ?: $this->controller->conditions($provider->query);

                if ($conditions) {
                    if (is_callable($conditions)) {
                        ($conditions)($provider->query);
                    } else {
                        $provider->query->andWhere($conditions);
                    }
                }

                foreach ($provider->models as $searchModel) {
                    $searchModel->scenario = $this->scenario;
                }

                return $provider;
            } else {
                return $searchModel;
            }
        } else {
            /** @var ActiveRecord|string $modelClass */
            $modelClass = $this->controller->{'modelClass'};
            $searchModel = new $modelClass(['scenario' => $this->scenario]);

            if (!$modelClass) {
                throw new Exception(get_class($this->controller) . "::modelClass must be set");
            }

            /** @var ActiveQuery $query */
            $query = $this->controller->query();
            $table = $query->from ? array_key_first($query->from) : $searchModel::tableName();
            $query->select(ModelHelper::columns($table, $searchModel->readable()));
            $query->with(Serializer::normalize(\Yii::$app->request->get('expand', [])));
            $conditions = $this->conditions ?: $this->controller->conditions($query);

            if ($conditions) {
                if (is_callable($conditions)) {
                    ($conditions)($query);
                } else {
                    $query->andWhere($conditions);
                }
            }

            $models = $query->all();
            foreach ($models as $searchModel) {
                $searchModel->scenario = $this->scenario;
            }

            return $models;
        }
    }
}
