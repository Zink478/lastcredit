<?php

namespace yii2custom\admin\core;

use himiklab\sortablegrid\SortableGridAction;
use Yii;
use yii\filters\AccessControl;

abstract class Controller extends \yii\web\Controller
{
    public $enableCsrfValidation = false;
    public $modelClass;

    public function init()
    {
        parent::init();

        if (is_null($this->modelClass)) {
            $modelName = cl($this, 'Controller');
            $this->modelClass = "common\\models\\$modelName";
        }
    }

    public function behaviors()
    {
        return array_filter([
            'access' => Yii::$app->user->identityClass ? [
                'class' => AccessControl::class,
                'rules' => [['allow' => Yii::can($this->id, $this->action->id)]],
            ] : null,
        ]);
    }

    public function actions()
    {
        return [
            'sort' => [
                'class' => SortableGridAction::class,
                'modelName' => $this->modelClass
            ]
        ];
    }

    public function render($view, $params = [])
    {
        return Yii::$app->request->isPjax
            ? $this->renderAjax($view, $params)
            : parent::render($view, $params);
    }
}