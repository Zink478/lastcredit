<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator \yii2custom\common\core\generators\crud\Generator */

/* @var $model \yii\db\ActiveRecord */
$model = new $generator->modelClass();
Yii::$app->has('languages') && Yii::$app->languages->enabled(false);
$safeAttributes = $model->safeAttributes();
if (empty($safeAttributes)) {
    $safeAttributes = $model->attributes();
}
Yii::$app->has('languages') && Yii::$app->languages->enabled(true);

echo "<?php\n";
?>

/* @var $this yii\web\View */
/* @var $model <?= ltrim($generator->modelClass, '\\') ?> */
/* @var $form \yii2custom\admin\core\ActiveForm */
?>

<div class="<?= Inflector::camel2id(StringHelper::basename($generator->modelClass)) ?>-form">
<?php foreach ($generator->getColumnNames() as $attribute) {
    if ($attribute == 'id') {
        continue;
    }

    if (substr($attribute, -strlen('_id')) == '_id' && !in_array($attribute, $generator::RESERVED_ATTRIBUTES)) {
        $model = ns($generator->modelClass) . '\\' .Inflector::camelize(substr($attribute, 0, -strlen('_id')));
        echo "    <?= \$form->field(\$model, '$attribute')->select($model::list()) ?>\n";
    } else if (in_array($attribute, $safeAttributes) && !in_array($attribute, $generator::RESERVED_ATTRIBUTES)) {
        echo "    <?= " . $generator->generateActiveField($attribute) . " ?>\n";
    }

    else if ($attribute == 'image_id') {
        echo "    <?= \$form->field(\$model, '".$attribute."')->image() ?>\n";
    }
} ?>
</div>
<?php Yii::$app->has('languages') && Yii::$app->languages->enabled(true) ?>
