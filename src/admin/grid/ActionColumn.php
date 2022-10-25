<?php

namespace yii2custom\admin\grid;

use Yii;
use yii\helpers\Url;

class ActionColumn extends \kartik\grid\ActionColumn
{
    public $query = [];
    public $buttonOptions = ['data-pjax' => '1'];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        foreach (['view', 'update', 'delete'] as $action) {
            if (!isset($this->visibleButtons[$action])) {
                $this->visibleButtons[$action] = Yii::can($action);
            } elseif (is_callable($this->visibleButtons[$action])) {
                if (Yii::can($action)) {
                    $func = $this->visibleButtons[$action];
                    $this->visibleButtons[$action] = $func;
                } else {
                    $this->visibleButtons[$action] = false;
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function createUrl($action, $model, $key, $index)
    {
        if (is_callable($this->urlCreator)) {
            return call_user_func($this->urlCreator, $action, $model, $key, $index, $this);
        }

        $params = is_array($key) ? $key : ['id' => (string)$key];
        $params[0] = $this->controller ? $this->controller . '/' . $action : $action;

        if ($this->query) {
            $params = array_merge($params, $this->query);
        }

        return Url::toRoute($params);
    }

    /**
     * @inheritdoc
     */
    protected function initDefaultButtons()
    {
        $isBs4 = $this->grid->isBs4();
        $this->setDefaultButton('view', Yii::t('kvgrid', 'View'), $isBs4 ? 'share' : 'eye-open');
        $this->setDefaultButton('update', Yii::t('kvgrid', 'Update'), $isBs4 ? 'pencil-alt' : 'pencil');
        $this->setDefaultButton('delete', Yii::t('kvgrid', 'Delete'), $isBs4 ? 'trash-alt' : 'trash');
    }
}