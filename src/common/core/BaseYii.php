<?php

namespace yii2custom\common\core;

$dir = realpath(__DIR__ . '/../../../../../') . '/yiisoft/yii2';
require_once $dir . '/BaseYii.php';

use Yii;

/**
 * @property \yii2custom\common\core\Application | \yii2custom\console\core\Application $app
 */
abstract class BaseYii extends \yii\BaseYii
{
    public static function can($controller = null, $actions = null, $strict = false): bool
    {
        // url
        if (is_array($controller) && count($controller)) {
            $parsed = explode('/', $controller[0]);
            $controller = $parsed[0];
        }

        // only action
        if (is_null($actions)) {
            $actions = $controller;
            $controller = null;
        }

        // defaults
        $controller = $controller ?? Yii::$app->controller->id;
        $actions = $actions ?? Yii::$app->controller->action->id;

        if (static::$app->user->can($controller)) {
            return true;
        }

        foreach ((array)$actions as $action) {
            foreach (static::getPermissions($controller, $action, $strict) as $permission) {
                if (static::$app->user->can($permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    ///
    /// Private
    ///

    private static $actionsMap = [
        'write' => ['create', 'delete', 'sort'],
        'i18n' => ['update'],
        'read' => ['index', 'view'],
    ];

    private static function getPermissions($controller, $action, $strict = false)
    {
        $result = [$controller . '-' . $action];
        if (!$strict) {
            foreach (static::$actionsMap as $permission => $actions) {
                if (in_array($action, $actions)) {
                    $result[] = $controller . '-' . $permission;
                }
            }
        }

        return $result;
    }
}