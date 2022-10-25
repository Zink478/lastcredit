<?php

namespace yii2custom\admin\form;

use dosamigos\ckeditor\CKEditorWidgetAsset;
use yii\helpers\Json;
use yii\web\View;

class CKEditor extends \dosamigos\ckeditor\CKEditor
{
    public function init()
    {
        parent::init();

        if (!$this->getId(false)) {
            $this->id = $this->options['id'] ?? null;
        } else {
            $this->options['id'] = $this->id;
        }
    }
}