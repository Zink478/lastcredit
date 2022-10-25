<?php

namespace yii2custom\admin\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\StringHelper;
use yii\helpers\Url;

class MenuWidget extends Widget
{
    public $items = [];

    public function run()
    {
        $items = $this->items;

        foreach ($items as $i => $group) {
            if (!$this->can($group)) {
                unset ($items[$i]);
            } else {
                if (isset($group['sub'])) {
                    foreach ($group['sub'] as $j => $item) {
                        if (!$this->can($item)) {
                            unset ($items[$i]['sub'][$j]);
                        }
                    }
                }
            }
        }

        return ob(function () use ($items) { ?>
            <? foreach ($items as $group): ?>
                <li class="nav-item has-treeview<?= $group['open'] ? ' menu-open' : '' ?>">
                    <div class="nav-link noselect">
                        <i class="nav-icon fas fa-<?= $group['icon'] ?>"></i>
                        <p><?= StringHelper::truncate($group['label'], 29) ?>
                            <? if ($group['sub'] ?? null): ?>
                                <i class="right fas fa-angle-left"></i>
                            <? endif ?></p>
                    </div>

                    <? if ($group['sub'] ?? null): ?>
                        <?= $this->subItems($group['sub']) ?>
                    <? endif ?>
                </li>
            <? endforeach ?>
        <? }); ?>
    <? }

    protected function subItems(array $items)
    { ?>
        <ul class="nav nav-treeview">
            <? foreach (($items ?? []) as $item): ?>
                <? if (!$item): ?><? return ?><? endif ?>
                <? $url = self::getUrl($item) ?>
            <li class="nav-item<?= self::isOpened($item) ? ' menu-open' : '' ?>">
                <<?= empty($item['sub']) ? 'a href="' . $url . '"' : 'span' ?>
                class="nav-link <?= self::isActive($item) ? 'active' : '' ?>">
                <? if (!empty($item['sub'])): ?><a href="<?= $url ?>" onclick="location.href = this.href"><? endif ?>
                <i class="far fa-<?= $item['icon'] ?? 'circle' ?> nav-icon" style="zoom: 0.66"></i>
                <? if (!empty($item['sub'])): ?></a><? endif ?>
                <p><? if (!empty($item['sub'])): ?><a href="<?= $url ?>"
                                                      onclick="location.href = this.href"><? endif ?><?= StringHelper::truncate($item['label'],
                            29) ?><? if (!empty($item['sub'])): ?></a><? endif ?></p>
                <? if (!empty($item['sub'])): ?>
                    <i class="right fas fa-angle-left"></i>
                <? endif ?>
                </<?= empty($item['sub']) ? 'a' : 'span' ?>>

                <? if (!empty($item['sub'])): ?>
                    <?= $this->subItems($item['sub']) ?>
                <? endif ?>
                </li>
            <? endforeach ?>
        </ul>
    <? }

    protected static function can(array &$item): bool
    {
        $result = false;

        if (isset($item['can'])) {
            return is_bool($item['can']) ? $item['can'] : Yii::$app->user->can($item['can']);
        } elseif (!empty($item['controller'])) {
            $controller = $item['controller'];
            $action = ($item['action'] ?? 'index');
            $result = Yii::can($controller, $action);
        } elseif (!empty($item['sub'])) {
            foreach ($item['sub'] as $subItem) {
                if (static::can($subItem)) {
                    $result = true;
                }
            }
        }

        return $item['can'] = $result;
    }

    protected static function getUrl(array $item): ?string
    {
        if (empty($item['url'])) {
            $params = $item['query'] ?? [];
            if (!isset($item['controller'])) {
                return false;
            }
            $url = '/' . $item['controller'] . (!empty($item['action']) ? '/' . $item['action'] : '');
            array_unshift($params, $url);
            return Url::to($params);
        } else {
            return $item['url'];
        }
    }

    protected static function isActive(array $item): bool
    {
        if (isset($item['active'])) {
            return !empty($item['active']);
        } else {
            if (empty($item['controller'])) {
                return false;
            }

            $result = (
                $item['controller'] == Yii::$app->controller->id &&
                (empty($item['action']) || $item['action'] == Yii::$app->controller->action->id)
            );

            if ($result) {
                if (!empty($item['query'])) {
                    foreach ($item['query'] as $param => $value) {
                        if (Yii::$app->request->get($param) == $value) {
                            return true;
                        }
                    }
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function isOpened(array $item): bool
    {
        if (self::isActive($item)) {
            return true;
        }

        foreach ($item['sub'] ?? [] as $subItem) {
            if (self::isActive($subItem) || self::isOpened($subItem)) {
                return true;
            }
        }

        return false;
    }
}