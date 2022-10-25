<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\crud\Generator */

$urlParams = $generator->generateUrlParams();
$modelClassName = Inflector::camel2words(StringHelper::basename($generator->modelClass));
$attributes = (new $generator->modelClass)->{'attributes'}();

echo "<?php\n";
?>

use yii2custom\admin\core\ActiveForm;
use yii2custom\admin\widgets\ActionHeaderWidget;
use yii2custom\admin\widgets\FormFooterWidget;
<?php if (in_array('title', $attributes)): ?>
use yii\helpers\StringHelper;
<?php endif ?>

/* @var $this yii\web\View */
/* @var $model <?= ltrim($generator->modelClass, '\\') ?> */

<?php if (in_array('title', $attributes)): ?>
$this->title = <?= $generator->generateString(Inflector::camel2words(StringHelper::basename($generator->modelClass))) ?> . ': ' . StringHelper::truncate($model->title, 30);
<?php else: ?>
$this->title = <?= $generator->generateString(Inflector::camel2words(StringHelper::basename($generator->modelClass))) ?> . ': ' . $model->id;
<?php endif ?>
$this->params['breadcrumbs'][] = ['label' => <?= $generator->generateString(Inflector::pluralize(Inflector::camel2words(StringHelper::basename($generator->modelClass)))) ?>, 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model-><?= $generator->getNameAttribute() ?>, 'url' => ['view', <?= $urlParams ?>]];
$this->params['breadcrumbs'][] = <?= $generator->generateString('Update') ?>;
?>

<div class="<?= Inflector::camel2id(StringHelper::basename($generator->modelClass)) ?>-update card">

    <div class="card-header">
        <?= '<?=' ?> ActionHeaderWidget::widget() ?>
    </div>

    <?= '<? ' ?>$form = ActiveForm::begin([
        'i18nOnly' => !Yii::can(null, 'update', true)
    ]) ?>

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
