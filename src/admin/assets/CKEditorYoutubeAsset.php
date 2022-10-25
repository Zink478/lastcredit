<?php

namespace yii2custom\admin\assets;

use dosamigos\ckeditor\CKEditorAsset;
use dosamigos\ckeditor\CKEditorWidgetAsset;
use kartik\widgets\AssetBundle;
use yii\web\View;

class CKEditorYoutubeAsset extends AssetBundle
{
    public $sourcePath = '@vendor/diembzz/yii2custom/web/ckeditor/plugins/youtube';
    public $depends = [CKEditorWidgetAsset::class];

    public function registerAssetFiles($view)
    {
        $src = \Yii::$app->assetManager->getAssetUrl($this, 'plugin.js');
        $view->registerJs("CKEDITOR.plugins.addExternal('youtube', '$src')", View::POS_END);
        parent::registerAssetFiles($view);
    }
}
