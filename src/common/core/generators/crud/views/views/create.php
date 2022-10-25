<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\crud\Generator */

echo "<?php\n";
?>

use yii2custom\admin\core\ActiveForm;
use yii2custom\admin\widgets\ActionHeaderWidget;
use yii2custom\admin\widgets\FormFooterWidget;

/* @var $this yii\web\View */
/* @var $model <?= ltrim($generator->modelClass, '\\') ?> */

$this->title = <?= $generator->generateString(Inflector::camel2words(StringHelper::basename($generator->modelClass))) ?> . ': ' . <?= $generator->generateString('Create') ?>;
$this->params['breadcrumbs'][] = ['label' => <?= $generator->generateString(Inflector::pluralize(Inflector::camel2words(StringHelper::basename($generator->modelClass)))) ?>, 'url' => ['index']];
$this->params['breadcrumbs'][] = <?= $generator->generateString('Create') ?>;
?>

<div class="<?= Inflector::camel2id(StringHelper::basename($generator->modelClass)) ?>-create card">

    <div class="card-header">
        <?= '<?=' ?> ActionHeaderWidget::widget() ?>
    </div>

    <?= '<? ' ?>$form = ActiveForm::begin() ?>

    <div class="card-body">
        <?= '<?' ?> if ($model->hasErrors("")): ?>
          <div class="error-summary">
            <?= '<?' ?>= $model->getFirstError("") ?>
          </div>
        <?= '<?' ?> endif ?>

        <?= '<?= ' ?>$this->render('_form', [
            'model' => $model,
            'form' => $form
        ]) ?>
    </div>

    <div class="card-footer">
        <?= '<?= ' ?>FormFooterWidget::widget(['form' => $form, 'model' => $model]) ?>
    </div>

    <?= '<? ' ?>ActiveForm::end() ?>

</div>
