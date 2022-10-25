<?php

namespace yii2custom\api\core\actions;

use Yii;
use yii\web\ServerErrorHttpException;
use yii2custom\common\core\ActiveRecord;

class CreateAction extends \yii\rest\CreateAction
{
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        /** @var ActiveRecord $model */
        $path = Yii::$app->request->get('path');
        $model = $path
            ? $this->controller->getModelByPath($path)
            : new $this->modelClass();

        $result = true;
        $model->scenario = $this->scenario;

        if (!Yii::$app->request->isGet) {
            $model->load(Yii::$app->request->post());
            $result = $model->save();
        }

        if ($result) {
            Yii::$app->getResponse()->setStatusCode(201);
            if (is_object($result) && !$model->hasErrors()) {
                return $result;
            }
        } else if (Yii::$app->request->isGet) {
            $model->loadDefaultValues();
        } elseif (!$model->hasErrors()) {
            throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
        }

        return $model;
    }
}
