<?php

namespace yii2custom\common\core;

use yii\rbac\Assignment;
use yii\rbac\PhpManager;

class AuthManager extends PhpManager
{
    public $itemFile = '@common/rbac/items.php';
    public $assignmentFile = '@common/rbac/assignments.php';
    public $ruleFile = '@common/rbac/rules.php';

    /**
     * {@inheritdoc}
     */
    public function getAssignments($userId)
    {
        static $result = null;
        if ($userId && $userId == \Yii::$app->user->id) {
            if (is_null($result)) {
                $user = \Yii::$app->user->identity;
                $this->assignments = $result = [$user->role => new Assignment([
                    'userId' => $user->id,
                    'roleName' => $user->role,
                    'createdAt' => $user->created_at,
                ])];
            }

            return $result;
        }

        return parent::getAssignments($userId);
    }
}
