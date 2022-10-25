<?php

namespace yii2custom\api\auth;

use yii\filters\auth\AuthMethod;

class CookieAuth extends AuthMethod
{
    /**
     * @var string the parameter name for passing the access token
     */
    public $tokenParam = 'access-token';

    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        $accessToken = $request->cookies->getValue($this->tokenParam);

        if (is_string($accessToken)) {
            $identity = $user->loginByAccessToken($accessToken, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }

        if ($accessToken !== null) {
            $this->handleFailure($response);
        }

        return null;
    }
}