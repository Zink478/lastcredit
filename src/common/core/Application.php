<?php

namespace yii2custom\common\core;

use Yii;
use yii\base\Controller;
use yii\base\InvalidRouteException;

class Application extends \yii\web\Application
{
    const APP_API = 'app-api';
    const APP_ADMIN = 'app-admin';
    const APP_CONSOLE = 'app-console';

    public function isApi(): bool
    {
        return Yii::$app->id == \common\core\Application::APP_API;
    }

    public function isAdmin(): bool
    {
        return Yii::$app->id == Application::APP_ADMIN;
    }

    public function isConsole(): bool
    {
        return Yii::$app->id == Application::APP_CONSOLE;
    }

    public function runAction($route, $params = [])
    {
        /* @var $controller Controller */
        $parts = $this->createController($route);

        if (is_array($parts)) {
            list($controller, $actionID) = $parts;

            $old = $this->controller ? [
                'controller' => $this->controller,
                'viewTitle' => $this->controller->view->title,
                'viewParams' => $this->controller->view->params,
                'queryParams' => $this->request->queryParams,
            ] : null;

            $this->controller = $controller;
            $this->request->setQueryParams($params);

            if ($old) {
                $this->controller->layout = false;
                $this->controller->view->params = [];
            }

            $result = $this->controller->runAction($actionID, $params);

            if ($old) {
                $this->controller = $old['controller'];
                $this->controller->view->title = $old['viewTitle'];
                $this->controller->view->params = $old['viewParams'];
                $this->request->setQueryParams($old['queryParams']);
                $this->controller->layout = null;
            }

            return $result;
        }

        $id = $this->getUniqueId();
        throw new InvalidRouteException(
            'Unable to resolve the request "'
            . ($id === '' ? $route : $id . '/' . $route)
            . '".'
        );
    }
}