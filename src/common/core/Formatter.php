<?php

namespace yii2custom\common\core;

use yii\helpers\Html;
use yii\helpers\StringHelper;

class Formatter extends \yii\i18n\Formatter
{
    public function asUrl($value, $options = [])
    {
        if (substr($value, 0, 1) == '/') {
            $value = \Yii::getAlias('@host') . $value;
        }

        if ($value === null) {
            return $this->nullDisplay;
        }

        $url = $value;

        if (substr($value, 0, 2) === '//') {
            $value = substr($value, 2);
        }

        return Html::a(Html::encode($value), $url, $options);
    }

    public function asMedia($value, $w = 50, $h = 50, $options = [])
    {
        $w = $w + $w;
        $h = $h + $h;

        $options['width'] = $options['width'] ?? $w / 2;
        return $this->asImage(\Yii::getAlias('@api-web') . "/media/{$w}x{$h}{$value}", $options);
    }

    public function asNtext($value, $length = 50)
    {
        if ($value === null) {
            return $this->nullDisplay;
        }

        $value = strip_tags($value);

        if ($length) {
            $value = StringHelper::truncate($value, $length);
        }

        return nl2br($value);
    }

    public function asForeignLink($value, string $title = null)
    {
        return Html::a($value, $title ?? $value, ['target' => '_blank', 'rel' => 'noreferrer']);
    }

    public function asNtextFull($value)
    {
        return $this->asNtext($value, false);
    }

    public function asBoolean($value)
    {
        if ($value === null) {
            return $this->nullDisplay ?? null;
        }

        return $value ? $this->booleanFormat[1] : $this->booleanFormat[0];
    }
}