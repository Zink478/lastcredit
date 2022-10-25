<?php

namespace yii2custom\api\core\actions;

use Yii;
use yii\helpers\Inflector;
use yii\rest\Action;
use yii\web\ServerErrorHttpException;
use yii2custom\api\core\Controller;
use yii2custom\common\core\ActiveRecord;

/**
 * @property Controller $controller
 */
class ValidateAction extends Action
{
    public $scenario;

    public function run($id = null)
    {
        $path = Yii::$app->request->get('path');
        $data = Yii::$app->request->post();
        $options = $data['_'] ?? [];
        $scenario = $options['scenario'] ?? null;
        unset($data['_']);

        /** @var $model ActiveRecord */
        /** @var $owner ActiveRecord */
        /** @var $current ActiveRecord */

        $model = null;
        $name = null;

        if ($id) {
            $model = $this->findModel($id);
        } else if ($path) {
            $model = $this->controller->getModelByPath($path);
        } else {
            $model = new $this->modelClass();
        }

        $model->scenario = $this->scenario
            ?? $model->isNewRecord
                ? $model::SCENARIO_CREATE
                : $model::SCENARIO_UPDATE;

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        $model->load(Yii::$app->request->post());

        $success = $model->owner
            ? $model->owner->validate([$name])
            : $model->validate();

        if (!$success) {
            Yii::$app->response->statusCode = 400;
            if (!$model->hasErrors()) {
                throw new ServerErrorHttpException('Failed to validate the object for unknown reason.');
            }
        }

        return $model;
    }

    public function findModel($id)
    {
        /** @var ActiveRecord $model */
        $model = $this->controller->findModel($id);
        $model->scenario = $this->scenario;
        return $model;
    }
}
