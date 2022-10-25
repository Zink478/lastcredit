<?php

namespace yii2custom\admin\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;

class ActionHeaderWidget extends Widget
{
    public $left = [];
    public $right = [];
    public $params = [];
    public $controller;
    public $action;

    public function init()
    {
        parent::init();
        self::prepare();
    }

    public function run()
    {
        return ob(function () { ?>
            <? foreach (['left', 'right'] as $side): ?>
                <? if (!$this->{$side}): ?>
                    <? continue ?>
                <? endif ?>
                <div class="float-<?= $side ?>">
                    <? foreach ($this->{$side} as $index => $button): ?>
                        <? $can = ($button && self::can($button, $index)) ?>
                        <? if ($index == 'delete' && $can): ?>
                            <?= Html::a($button['title'], (array)$button['url'], [
                                'class' => 'btn btn-' . $button['class'],
                                'aria-label' => $button['title'],
                                'target' => $button['target'] ?? null,
                                'data' => $button['data'] ?? [
                                        'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                                        'method' => 'post',
                                        'pjax' => '0'
                                    ],
                            ]) ?>&nbsp;
                        <? elseif ($can): ?>
                            <?= Html::a($button['title'], $button['url'],
                                ['class' => 'btn btn-' . $button['class'], 'target' => $button['target'] ?? null, 'data' => $button['data'] ?? null]) ?>&nbsp;
                        <? endif ?>
                    <? endforeach ?>
                </div>
            <? endforeach ?>
        <? }); ?>
    <? }

    protected static function can(array $item, $permissions): bool
    {
        $can = $item['can'] ?? null;
        $url = $item['url'] ?? null;

        if (is_bool($can)) {
            return $can;
        } elseif ($can) {
            return Yii::$app->user->can($can);
        } elseif (($permissions && Yii::can($permissions)) || Yii::can($url)) {
            return true;
        }

        return false;
    }

    protected function prepare()
    {
        $id = Yii::$app->request->get('id');
        $this->action = $this->action ?? Yii::$app->controller->action->id;
        $this->controller = $this->controller ?? Yii::$app->controller->id;

        if ($this->action != 'create') {
            $defaults['left'] = ['create'];
            if ($this->action != 'index') {
                $defaults['left'] = array_merge($defaults['left'],
                    [($this->action == 'update' ? 'view' : 'update'), 'delete']);
            }
        } else {
            $defaults['left'] = [];
        }

        if ($this->action != 'index') {
            $defaults['right'] = ['index'];
        } else {
            $defaults['right'] = [];
        }

        $left = array_combine($defaults['left'], array_fill(0, count($defaults['left']), true));
        $right = array_combine($defaults['right'], array_fill(0, count($defaults['right']), true));
        $left = ($this->left === false) ? [] : array_merge($left, $this->left);
        $right = ($this->right === false) ? [] : array_merge($right, $this->right);

        foreach (['left' => $left, 'right' => $right] as $side => $buttons) {
            foreach ($buttons as $index => $button) {
                if ($button) {
                    $default = [
                        'class' => 'outline-primary',
                        'title' => Yii::t('app', ucfirst($index)),
                        'url' => ($id ? [
                            $index,
                            'id' => $id
                        ] : [($this->controller ? $this->controller . '/' : '') . $index])
                    ];

                    if ($index == 'index') {
                        $default['url'] = [($this->controller ? $this->controller . '/' : '') . $index];
                    } elseif ($index == 'create') {
                        $default['url'] = [($this->controller ? $this->controller . '/' : '') . $index];
                        $default['class'] = 'success';
                    } elseif ($index == 'view' || $index == 'update') {
                        $default['class'] = 'primary';
                    } elseif ($index == 'delete') {
                        $default['class'] = 'danger';
                    }

                    $default['url'] += $this->params;

                    ${$side}[$index] = array_merge($default, $button === true ? [] : $button);
                }
            }
        }

        $this->left = $left;
        $this->right = $right;
    }
}