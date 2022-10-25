<?php

namespace yii2custom\admin\core;

use yii2custom\common\core\ActiveRecord;
use yii\helpers\Inflector;

/**
 * @method ActiveField field($model, $attribute, $options = [])
 */
class ActiveForm extends \kartik\form\ActiveForm
{
    /** @var bool Allow to edit only i18n translates */
    public $i18nOnly = false;
    public $fieldClass = ActiveField::class;

    public static function autoSlug(ActiveRecord $model, $titleAttribute = 'title', $slugAttribute = 'slug')
    {
        $slugSelector = self::_selector($model, $slugAttribute);
        $titleSelector = self::_selector($model, $titleAttribute . '_' . \Yii::$app->languages->default());
        \Yii::$app->view->registerJs("$(function() {
            $('$titleSelector').on('keyup change', function (){
                $('$slugSelector').val(slug(this.value));
            });
            $('$slugSelector').on('keyup change', function (){
                $('$slugSelector').val(slug(this.value));
            });
        })");
    }

    protected static function _selector(ActiveRecord $model, $attribute)
    {
        return '#' . self::_id($model, $attribute) . '[name="' . self::_name($model, $attribute) . '"]';
    }

    protected static function _name(ActiveRecord $model, $attribute)
    {
        return ($model->formName() ? $model->formName() . "[$attribute]" : $attribute);
    }

    protected static function _id(ActiveRecord $model, $attribute)
    {
        return Inflector::underscore($model->formName() ? $model->formName() . "-$attribute" : $attribute);
    }
}