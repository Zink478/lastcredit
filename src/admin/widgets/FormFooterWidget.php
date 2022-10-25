<?php

namespace yii2custom\admin\widgets;

use yii2custom\admin\core\ActiveForm;
use yii2custom\common\core\ActiveRecord;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

class FormFooterWidget extends Widget
{
    /** @var ActiveForm */
    public $form;

    /** @var ActiveRecord */
    public $model;

    public function run()
    {
        return ob(function () {
            $model = $this->model;
            $form = $this->form;
            $createdAt = ($model->hasAttribute('created_at') && $model->getAttribute('created_at')) ? Yii::$app->formatter->asDate($model->{'created_at'}) : null;
            $updatedAt = ($model->hasAttribute('updated_at') && $model->getAttribute('updated_at')) ? Yii::$app->formatter->asDate($model->{'updated_at'}) : null;
            $createdBy = ($model->hasAttribute('created_by') && $model->getAttribute('created_by')) ? $model->{'createdBy'} : null;
            $updatedBy = ($model->hasAttribute('updated_by') && $model->getAttribute('updated_by')) ? $model->{'updatedBy'} : null;
            ?>

            <div class="form-footer">
                <? if (!$form->readonly): ?>
                    <?= Html::submitButton(Yii::t('app', 'Save'), ['class' => 'btn btn-success']) ?>
                <? endif ?>

                <? if ($createdAt || $createdBy): ?>
                    <div class="form-footer-right float-right">
                        <div class="row">
                            <div class="col-12">
                                <?= Yii::t('app', 'Created') ?>
                                <? if ($createdAt): ?>
                                    <?= $createdAt ?>
                                <? endif ?>
                                <? if ($createdBy): ?>
                                    &nbsp;<a href="<?= Url::to([
                                        'manager/view',
                                        'id' => $createdBy->id
                                    ]) ?>"><?= $createdBy->email ?></a>
                                <? endif ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <?= Yii::t('app', 'Updated') ?>
                                <? if ($updatedAt): ?>
                                    <?= $updatedAt ?>
                                <? endif ?>
                                <? if ($updatedBy): ?>
                                    &nbsp;<a href="<?= Url::to([
                                        'manager/view',
                                        'id' => $updatedBy->id
                                    ]) ?>"><?= $updatedBy->email ?></a>
                                <? endif ?>
                            </div>
                        </div>
                    </div>
                <? endif ?>
            </div>
        <? }); ?>
    <? }
}