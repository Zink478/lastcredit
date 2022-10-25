<?php

namespace yii2custom\api\core\actions;

use yii2custom\common\core\ActiveRecord;

class ViewAction extends \yii\rest\ViewAction
{
    public $scenario = ActiveRecord::SCENARIO_VIEW;

    public function findModel($id)
    {
        /** @var ActiveRecord $model */
        $model = $this->controller->findModel($id);
        $model->scenario = $this->scenario;
        return $model;
    }
}