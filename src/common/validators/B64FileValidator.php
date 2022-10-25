<?php

namespace yii2custom\common\validators;

use yii\validators\Validator;

class B64FileValidator extends Validator
{
    protected const MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
    ];

    public $types = ['jpg', 'png', 'pdf', 'doc', 'docx', 'xlsx'];
    public $message = "{attribute} type is not valid, allowed only {types} files.";

    public function validateAttribute($model, $attribute)
    {
        $mime = $this->getType(substr($model->{$attribute}, 0, 100));
        $allowed = array_intersect(static::MIME, $this->types);

        if ($mime) {
            if (!in_array($mime, array_keys($allowed))) {
                $this->addError($model, $attribute, $this->message, [
                    'types' => join(', ', array_unique($this->types))
                ]);
            }
        }
    }

    private function getType($b64)
    {
        if (!$b64) {
            return null;
        }

        list($type, $data) = explode(';', substr($b64, strlen('data:')));
        list(, $data) = explode(',', $data);
        return $type;
    }
}
