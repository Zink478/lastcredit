<?php

namespace yii2custom\common\core;

use yii\helpers\Inflector;

class Widget extends \yii\base\Widget
{
    public function run()
    {
        return $this->render();
    }

    public function render($view = null, $params = [])
    {
        return parent::render($view ?? 'widget', $params);
    }

    public function getViewPath()
    {
        return preg_replace('#/widgets/views$#', '/views/widgets/', parent::getViewPath()) . Inflector::camel2id(cl(static::class, 'Widget'));
    }
}