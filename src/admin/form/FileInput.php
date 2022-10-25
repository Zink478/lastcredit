<?php

namespace yii2custom\admin\form;

class FileInput extends \kartik\widgets\FileInput
{
    public function init()
    {
        parent::init();

        $this->view->registerJs('
            $(() => {
               const input = $("#' . $this->field->inputId . '");
                $(input.get(0).form).submit(() => {
                   const hidden = input.closest(".file-input").prev();
                   const fileinput = input.data("fileinput");
                   const values = [];
                   $(fileinput.initialPreview).each(function() {
                      const name = this.match(/\/([^\/?#]+)[^\/]*$/)[0].substr(1).split(".").shift();
                      values.push(name);
                   });
                   hidden.val(values.join(","));
                });
            });
        ');

        $this->view->registerJs('
            $((ev) => {
                const input = $("#' . $this->field->inputId . '");
                const container = input.closest(".file-input");
                container.on("mousedown", ".kv-file-remove", (event) => {
                    let target = $(event.target);
                    if (target.tagName != "BUTTON") {
                        target = $(event.target).closest("button")
                    }
                    
                    const current = $(target).data("key");
                    const fileinput = input.data("fileinput");
                    
                    if (fileinput) {
                        for (const i in fileinput.initialPreviewConfig) {
                            const item = fileinput.initialPreviewConfig[i];
                            if (item.key == current) {
                                const frame = input.closest(".file-input")
                                    .find(".kv-file-remove[data-key="+ current +"]")
                                    .closest(".file-preview-frame");

                                fileinput.initialPreviewConfig.splice(i, 1);
                                fileinput.initialPreview.splice(i, 1);
                                frame.fadeOut(600, function() {
                                    $(this).remove();
                                });
                            }
                        }
                    }
                });
            })
        ');
    }
}