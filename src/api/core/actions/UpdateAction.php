<?php

namespace yii2custom\api\core\actions;

use Yii;
use yii\web\ServerErrorHttpException;
use yii2custom\common\core\ActiveRecord;

class UpdateAction extends \yii\rest\UpdateAction
{
    public $scenario = ActiveRecord::SCENARIO_UPDATE;

    public function run($id)
    {
        /* @var $model ActiveRecord */
        $model = $this->findModel($id);
        $model->scenario = $this->scenario;
        $this->checkAccess && call_user_func($this->checkAccess, $this->id, $model);

        if ($model->load(Yii::$app->request->post())) {
            if (!$model->save()) {
                Yii::$app->response->statusCode = 400;
                if (!$model->hasErrors()) {
                    throw new ServerErrorHttpException('Failed to update the object for unknown reason.');
                }
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
