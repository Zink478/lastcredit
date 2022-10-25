<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator \yii2custom\common\core\generators\crud\Generator */

$passedAttributes = ['priority'];
$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

echo "<?php\n";
?>

use <?= $generator->indexWidgetType === 'grid' ? "yii2custom\\admin\\core\\GridView" : "yii\\widgets\\ListView" ?>;
use yii2custom\admin\widgets\ActionHeaderWidget;
<?= $generator->enablePjax ? 'use yii\widgets\Pjax;' : '' ?>

/* @var $this yii\web\View */
<?= !empty($generator->searchModelClass) ? "/* @var \$searchModel " . ltrim($generator->searchModelClass, '\\') . " */\n" : '' ?>
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = <?= $generator->generateString(Inflector::pluralize(Inflector::camel2words(StringHelper::basename($generator->modelClass)))) ?>;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="<?= Inflector::camel2id(StringHelper::basename($generator->modelClass)) ?>-index card">

    <div class="card-header">
        <?= '<?=' ?> ActionHeaderWidget::widget() ?>
    </div>

<?= $generator->enablePjax ? "    <?php Pjax::begin(); ?>\n" : '' ?>
<?php if ($generator->indexWidgetType === 'grid'): ?>
    <div class="card-body">
        <?= "<?= " ?>GridView::widget([
            'dataProvider' => $dataProvider,
            <?= !empty($generator->searchModelClass) ? "'filterModel' => \$searchModel,\n            'columns' => [\n" : "'columns' => [\n"; ?>
<?php
$count = 0;
if (($tableSchema = $generator->getTableSchema()) === false) {
    foreach ($generator->getColumnNames() as $name) {
        if (++$count < 6) {
            echo "                '" . $name . "',\n";
        } else {
            echo "                //'" . $name . "',\n";
        }
    }
} else {
    foreach ($tableSchema->columns as $column) {
        if (in_array($column->name, $passedAttributes)) {
            continue;
        }
        $format = $generator->generateColumnFormat($column);
        if ($count < 6) {
            if ($column->name == 'id') {
                echo "                ['class' => '\yii2custom\admin\grid\IDColumn'],\n\n";
            } else if ($column->name == 'image_id') {
                echo "                [\n" .
                    "                    'class' => '\yii2custom\admin\grid\ImageColumn',\n" .
                    "                    'attribute' => 'image_id',\n" .
                    "                ],\n";
            } else if (in_array($column->name, $generator::RESERVED_ATTRIBUTES)) {
                continue;
            } else if (substr($column->name, -strlen('_id')) == '_id') {
                echo "                [\n" .
                    "                    'class' => '\yii2custom\admin\grid\RelationColumn',\n" .
                    "                    'attribute' => '{$column->name}',\n" .
                    "                ],\n";
            } else {
                echo "                '" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
            }

            $count++;
        } else {
            echo "                //'" . $column->name . ($format === 'text' ? "" : ":" . $format) . "',\n";
        }
    }
}
?>

                ['class' => '\yii2custom\admin\grid\ActionColumn'],
            ],
        ]); ?>
    </div>
<?php else: ?>
    <?= "<?= " ?>ListView::widget([
        'dataProvider' => $dataProvider,
        'itemOptions' => ['class' => 'item'],
        'itemView' => function ($model, $key, $index, $widget) {
            return Html::a(Html::encode($model-><?= $nameAttribute ?>), ['view', <?= $urlParams ?>]);
        },
    ]) ?>
<?php endif; ?>

<?= $generator->enablePjax ? "    <?php Pjax::end(); ?>\n" : '' ?>
</div>
