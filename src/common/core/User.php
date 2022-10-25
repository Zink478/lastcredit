<?php

namespace yii2custom\common\core;

use Yii;

/**
 * @property \common\models\Manager $identity
 */
class User extends \yii\web\User
{
    public $identityClass = 'common\models\Manager';
    public $loginUrl = 'user/login';

    /**
     * @deprecated
     */
    public function validatePassword(string $password, string $hash)
    {
        return Yii::$app->security->validatePassword($password, $hash);
    }
}