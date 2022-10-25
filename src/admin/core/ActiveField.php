<?php

namespace yii2custom\admin\core;

use common\models\Topic;
use common\models\Video;
use kartik\select2\Select2;
use kartik\widgets\DateTimePicker;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\web\View;
use yii2custom\admin\assets\CKEditorFormatButtonsAsset;
use yii2custom\admin\assets\CKEditorVideoDetectorAsset;
use yii2custom\admin\assets\CKEditorYoutubeAsset;
use yii2custom\admin\form\CKEditor;
use yii2custom\admin\form\FileInput;
use yii2custom\admin\form\MultipleUpload;
use yii2custom\admin\form\Typeahead;
use yii2custom\common\core\ActiveRecord;

/**
 * @property Model|ActiveRecord $model
 * @property ActiveForm $form
 */
class ActiveField extends \kartik\form\ActiveField
{
    /** @var array self[] */
    protected $i18nFields = [];

    public function init()
    {
        parent::init();

        $this->initDisability($this->options);
        $this->initDisability($this->inputOptions);
    }

    ///
    /// Standard
    ///

    public function widget($class, $config = [])
    {
        return $this->i18nWrapper(function ($config) use ($class) {
            return parent::widget($class, $config);
        }, $config);
    }

    public function hiddenInput($options = [], $inputOnly = true)
    {
        if ($inputOnly) {
            $this->template = '{input}';
            $this->options['tag'] = false;
        }

        return parent::hiddenInput($options);
    }

    public function textInput($options = [])
    {
        return $this->i18nWrapper(function ($options) {
            return parent::textInput(array_merge(['maxlength' => true], $options));
        }, $options);
    }

    public function textarea($options = [])
    {
        return $this->i18nWrapper(function ($options) {
            return parent::textarea(array_merge(['rows' => 3], $options));
        }, $options);
    }

    ///
    /// Custom
    ///

    public function slug()
    {
        if ($this->model->isNewRecord) {
            if (!empty($this->model->errors[$this->attribute])) {
                $this->error();
                return $this->textInput(['disabled' => true]);
            }
        } else {
            return $this->textInput(['disabled' => true]);
        }
    }

    public function html($options = [])
    {
        static $autosizeJsRegistered = false;
        if (!$autosizeJsRegistered) {
            $autosizeJsRegistered = true;
            $this->form->view->registerJs("
                $(function () {
                    CKEDITOR.config.pasteFromWordRemoveStyles = false;
                    CKEDITOR.config.disableObjectResizing  = true
                    CKEDITOR.config.resize_enabled = false;
                    CKEDITOR.resize_dir = 'both';
                    CKEDITOR.on('instanceReady', function (evt) {
                        const editor = evt.editor;
                        const sizes = {};
                        editor.on('focus', function (e) {
                            sizes[e.editor.name] = e.editor.container.$.clientHeight;
                            e.editor.resize('100%', '720');
                        });
                        editor.on('blur', function (e) {
                            e.editor.resize('100%', sizes[e.editor.name]);
                        });
                    });
                });
            ", View::POS_READY, 'html-editor-autosize');
        }

        CKEditorFormatButtonsAsset::register($this->form->view);
        CKEditorYoutubeAsset::register($this->form->view);

        $options = ArrayHelper::merge([
            'clientOptions' => [
                'filebrowserUploadUrl' => '/media/ckeditor-upload',
                'filebrowserUploadMethod' => 'form',
                'toolbarGroups' => [
                    ['name' => 'clipboard', 'groups' => ['mode', 'undo', 'selection']],
                    ['name' => 'editing', 'groups' => ['tools', 'about']],
                    '/',
                    ['name' => 'paragraph', 'groups' => ['templates', 'list', 'align']],
                    ['name' => 'insert'],
                    '/',
                    ['name' => 'basicstyles', 'groups' => ['basicstyles', 'cleanup']],
                    ['name' => 'colors'],
                    ['name' => 'links'],
                    ['name' => 'others'],
                ],

                'embed_provider' => '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
                'removePlugins' => ['resize'],
                'removeButtons' => 'Smiley,Iframe,h1,h3,h4,h5,h6',
                'contentsCss' => ['//cdn.ckeditor.com/4.16.0/full-all/contents.css'],
                'extraPlugins' => ['format_buttons', 'youtube'],
                'allowedContent' => true
            ],
            'preset' => 'custom'
        ], $options);

        if (!empty($options['name']) && empty($options['options']['name'])) {
            $options['options']['name'] = $options['name'];
            unset($options['name']);
        }

        return $this->widget(CKEditor::class, $options);
    }

    public function select($data, $options = [])
    {
        if ($options['readonly'] ?? false) {
            $options['disabled'] = true;
        }

        $options = ArrayHelper::merge([
            'data' => $data,
            'options' => ['placeholder' => ''],
            'pluginOptions' => ['allowClear' => true],
        ], $options);

        if ($options['id'] ?? false) {
            $options['options']['id'] = $options['id'];
            unset($options['id']);
        }

        return $this->widget(Select2::class, $options);
    }

    public function tags($url, $options = [])
    {
        $models = $this->model->{$this->attribute};
        $options['pluginOptions']['ajax']['url'] = $url;
        $options['options']['value'] = ($options['options']['value'] ?? null)
            ?? array_column($models ?: [], 'title', 'id');

        $config = [
            'options' => ['placeholder' => '', 'multiple' => true, 'submitOnEnter' => true],
            'pluginOptions' => [
                'tags' => false,
                'allowClear' => true,
                'minimumInputLength' => 3,
                'ajax' => [
                    'cache' => true,
                    'dataType' => 'json',
                    'processResults' => new JsExpression("function (data, params) {
                        const results = [];
                        for (const [k, v] of Object.entries(data)) {
                            results.push({'id': k, 'text': v});
                        }
                        return {results: results}
                    }"),
                ],
            ],
        ];

        return $this->widget(Select2::class, ArrayHelper::merge($config, $options));
    }

    public function date($options = [])
    {
        return $this->widget(DateTimePicker::class, ArrayHelper::merge([
            'options' => [
                'placeholder' => '',
                'value' => $this->model->{$this->attribute}
                    ? Yii::$app->formatter->asDate($this->model->{$this->attribute})
                    : '',
            ],
            'convertFormat' => true,
            'pluginOptions' => [
                'autoclose' => true,
                'format' => 'yy/MM/dd',
                'minView' => 2
            ]
        ], $options));
    }

    public function dateTime($options = [])
    {
        return $this->widget(DateTimePicker::class, ArrayHelper::merge([
            'options' => [
                'placeholder' => '',
                'value' => $this->model->{$this->attribute}
                    ? Yii::$app->formatter->asDatetime($this->model->{$this->attribute})
                    : ''
            ],
            'convertFormat' => true,
            'pluginOptions' => [
                'autoclose' => true,
                'format' => 'yy/MM/dd hh:i'
            ]
        ], $options));
    }

    public function enum($options = [])
    {
        $group = $options['group'] ?? $this->attribute;
        unset($options['group']);
        return $this->select($this->model::enum($group), $options);
    }

    public function typehead()
    {
        return $this->widget(Typeahead::class, [
            'options' => ['placeholder' => 'Filter as you type ...'],
            'pluginOptions' => ['highlight' => true],
            'dataset' => [
                [
                    'display' => 'label',
                    'remote' => [
                        'url' => Url::to(['product/list']) . '?q=%QUERY',
                        'wildcard' => '%QUERY'
                    ],
                ]
            ]
        ]);
    }

    public function image($options = [])
    {
        return $this->_file(array_merge($options, ['multiple' => false]));
    }

    public function video($options = [])
    {
        return $this->_file(array_merge($options, ['multiple' => false, 'video' => true, 'accept' => 'video/mp4']));
    }

    public function images($options = [])
    {
        return $this->_file(array_merge($options, ['multiple' => true]));
    }

    protected function _file($options = [])
    {
        $initialPreview = [];
        $initialPreviewConfig = [];
        $files = $this->model->{$this->attribute};
        $options = array_merge(['accept' => 'image/jpeg, image/png', 'multiple' => false], $options);
        $isVideo = $options['video'] ?? false;
        unset($options['video']);

        if (!$options['multiple']) {
            $files = [$files];
        }

        foreach (array_filter($files) as $key => $file) {
            $size = file_exists(Yii::getAlias('@media') . $file) ? filesize(Yii::getAlias('@media') . $file) : 0;
            $initialPreview[] = Yii::getAlias('@media-web') . $file;
            $initialPreviewConfig[] = [
                'caption' => basename($file),
                'size' => $size,
                'key' => $key + 1
            ];
        }

        $config = [
            'options' => $options,
            'pluginOptions' => [
                'initialPreviewAsData' => true,
                'initialPreviewFileType' => $isVideo ? 'video' : 'image',
                'initialPreview' => $initialPreview,
                'initialPreviewConfig' => $initialPreviewConfig,
                'overwriteInitial' => $options['multiple'] ? false : true,
                'initialCaption' => ' ',
                'initialPreviewShowDelete' => true,
                'previewZoomButtonClasses' => [
                    'toggleheader' => 'd-none',
                    'fullscreen' => 'd-none',
                    'borderless' => 'd-none'
                ],
                'showUpload' => false,
                'showRemove' => false,
                'showClose' => false,
                'showCancel' => false,
            ]
        ];

        if (!$options['multiple']) {
            $config = ArrayHelper::merge($config, [
                'pluginOptions' => [
                    'fileActionSettings' => ['showDrag' => false],
                    'layoutTemplates' => ['actionDelete' => ''],
                ]
            ]);
        }

        return $this->widget(FileInput::class, $config);
    }

    ///
    /// I18n
    ///

    public function render($content = null, $i18n = true)
    {
        if (!$i18n || !$this->i18nIsNeed()) {
            return parent::render($content);
        }

        $fields = array_merge(
            [Yii::$app->languages->default() => $this],
            $this->i18nFields[$this->attribute] ?? []
        );

        /** @var self $field */
        foreach ($fields as $lang => $field) {
            $html = $field->render($content, false);
            if (count($fields) > 1) {
                $html = Yii::$app->languages->areaBegin($lang) . $html . Yii::$app->languages->areaEnd();
            }
            $result[] = $html;
        }

        $result = join("\n", $result);

        if (count($fields) > 1) {
            $result = '<div class="card"><div class="card-body">' . $result . '</div></div>';
        }

        return $result;
    }

    public function readonly()
    {
        return ($this->form->readonly || $this->form->disabled || $this->form->i18nOnly);
    }

    protected function i18nIsNeed(): bool
    {
        return (
            method_exists($this->model, 'i18n')
            && in_array($this->attribute, $this->model->i18n())
            && Yii::$app->has('languages') && Yii::$app->languages->enabled()
        );
    }

    protected function i18nLanguages($default = false)
    {
        $result = Yii::$app->languages->list($default);

        if ($this->readonly()) {
            $result = array_intersect($result, [Yii::$app->language, Yii::$app->languages->default()]);
        }

        return $result;
    }

    protected function i18nWrapper($callback, $config = [])
    {
        if ($this->i18nIsNeed()) {
            $id = $config['id'] ?? null;
            $attribute = $this->attribute;
            foreach ($this->i18nLanguages() as $lang) {
                $this->attribute = $attribute . '_' . $lang;
                $id && $config['id'] = $id . '_' . $lang;
                $field = $this->i18nFields[$attribute][$lang] = clone($callback($config));
                $id && $config['id'] = $id;
            }
            $this->attribute = $attribute;
        }

        return clone($callback($config));
    }

    protected function initDisability(&$options)
    {
        if ($this->readonly()) {
            $options['disabled'] = true;
            $options['readonly'] = true;
        }

        return parent::initDisability($options);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // IN DEV
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @in-dev
     * @return string
     */
    public function relation()
    {
        $relationClass = $this->model->getRelation($this->attribute)->modelClass;
        $js = $this->form->view->js;

        $name = $this->attribute;
        $path = Inflector::camel2id(cl($relationClass));
        $relation = $this->model->{$this->attribute} ?? new $relationClass();
        $content = $this->form->view->render("/$path/_form", ['form' => $this->form, 'model' => $relation]);
        $content = preg_replace('/id="([^"\\[]+)/', 'id="' . $name . '-$1', $content);
        $content = preg_replace('/for="([^"\\[]+)/', 'for="' . $name . '-$1', $content);
        $content = preg_replace('/name="([^"\\[]+)/', 'name="' . $name . '[$1]', $content);
        $content = preg_replace('/([\'\"]#)([^\'\"]+?)([\'\"])/', '$1' . $name . '-$2$3', $content);
        $content = preg_replace('/kv-plugin-loading loading-(.+?)/', 'kv-plugin-loading loading-' . $name . '-$1', $content); // Select2 fix

        if ($content) {
            foreach ($this->form->view->js as $pos => &$blocks) {
                foreach ($blocks as $key => &$block) {
                    if (!isset($js[$pos][$key])) { // Select2 fixes
                        $block = preg_replace('/jQuery\(\'#(.+?)\'\)/', 'jQuery(\'#' . $name . '-$1\')', $block);
                        $block = preg_replace('/initS2Loading\(\'(.+?)\'/', 'initS2Loading(\'' . $name . '-$1\'', $block);
                        $block = preg_replace('/kv-plugin-loading loading-(.+?)/', 'kv-plugin-loading loading-' . $name . '-$1', $block);
                    }
                }
            }
        }

        return \yii\helpers\Html::label($this->model->getAttributeLabel($this->attribute)) . "\n" . '
        <div class="card">
            <div class="card-body">
                ' . $content . '
            </div>
        </div>';
    }

}