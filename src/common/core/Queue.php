<?php

namespace yii2custom\common\core;

use yii2custom\common\exceptions\job\JobRetryException;

class Queue extends \yii\queue\redis\Queue
{
    public function init()
    {
        parent::init();

        $this->on(self::EVENT_AFTER_ERROR, function (\yii\queue\ExecEvent $event) {
            $event->retry = ($event->error instanceof JobRetryException) && ($event->attempt < $this->attempts);
        });
    }
}