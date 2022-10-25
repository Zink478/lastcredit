<?php

namespace yii2custom\common\traits\ActiveRecord;

use yii2custom\common\core\ActiveRecord;

/**
 * @mixin ActiveRecord
 */
trait TAuth
{
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    public static function findIdentity($id)
    {
        return static::class::findOne(['id' => $id, 'status' => 'active']);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::class::findOne(['auth_key' => $token, 'status' => 'active']);
    }

    public function getAuthKey()
    {
        return $this->auth_key;
    }
}