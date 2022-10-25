<?php

namespace yii2custom\admin\form;

use api\models\Product;
use yii2custom\common\core\ActiveRecord;

class Typeahead extends \kartik\widgets\Typeahead
{
    public function init()
    {
        parent::init();

        /** @var ActiveRecord $relation */
        $relation = $this->model->{substr($this->attribute, 0, -3)};
        $this->options['value'] = $relation ? $relation->{$relation::titleAttribute()} : '';

        $id = $this->options['id'];
        $this->options['name'] = '';
        $this->options['id'] = $this->options['id'] . '_input';

        $this->field->template = str_replace('{input}', '{input} {data-input}', $this->field->template);
        $this->field->parts['{data-input}'] = '<input data-label="" type="hidden" id="' . $id . '" name="' . $this->name . '">';

        $this->pluginEvents['typeahead:select'] = '(ev, val) => {
            $("#' . $id . '").val(val.value);
            $("#' . $id . '").data("label", val.label); 
        }';

        $this->pluginEvents['typeahead:idle'] = '(ev) => {
            if (!$(ev.target).val().length) {
                $("#' . $id . '").val("");
                $("#' . $id . '").data("label", ""); 
            } 
            $(ev.target).val($("#' . $id . '").data("label"));
        }';
    }
}