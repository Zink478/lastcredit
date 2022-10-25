<?php

namespace yii2custom\api\core\actions;

use yii\base\InvalidConfigException;
use yii\web\Response;

class AuthAction extends \yii\authclient\AuthAction
{
    protected function authSuccess($client)
    {
        if (!is_callable($this->successCallback)) {
            throw new InvalidConfigException('"' . get_class($this) . '::$successCallback" should be a valid callback.');
        }

        $response = call_user_func($this->successCallback, $client);
        if ($response instanceof Response) {
            return $response;
        }

        return $this->redirectSuccess();
    }
}