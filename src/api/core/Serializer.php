<?php

namespace yii2custom\api\core;

use yii\base\Arrayable;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

class Serializer extends \yii\rest\Serializer
{
    private $fields = null;
    private $expand = null;

    protected function getRequestedFields()
    {
        $expand = $this->expand ?? $this->request->get($this->expandParam, []);
        $fields = $this->fields ?? $this->request->get($this->fieldsParam, []);
        return [self::normalize($fields), self::normalize($expand)];
    }

    protected function serializeModels(array $models)
    {
        /** @var \yii2custom\common\core\ActiveRecord[] $models */

        $errors = false;
        foreach ($models as $model) {
            if ($model instanceof Arrayable && $model->hasErrors()) {
                $errors = true;
                break;
            }
        }

        foreach ($models as $i => $model) {
            if ($errors && $model instanceof Arrayable) {
                $models[$i] = $this->serializeModelErrors($model);
            } else {
                $models[$i] = $this->serialize($model);
            }
        }

        return $models;
    }

    protected function serializeModelErrors($model)
    {
        $this->response->setStatusCode(422, 'Data Validation Failed.');
        return $model->getFirstErrors();
    }

    protected function serializeModel($model)
    {
        $result = parent::serializeModel($model);
        if ($model instanceof ActiveRecord && $model->isNewRecord) {
            $result = array_diff_key($result, array_flip($model->primaryKey()));
        }
        return $result;
    }

    public function serialize($data, $fields = null, $expand = null)
    {
        if (!is_null($fields)) {
            $this->fields = $fields;
        }

        if (!is_null($expand)) {
            $this->expand = $expand;
        }

        if (is_array($data) && current($data) instanceof Arrayable) {
            $data = new ActiveDataProvider(['models' => $data, 'pagination' => false]);
        }

        $this->preserveKeys = true;
        $result = parent::serialize($data);

        if (!is_null($fields)) {
            $this->fields = null;
        }

        if (!is_null($expand)) {
            $this->expand = null;
        }

        return $result;
    }

    public static final function normalize($data): array
    {
        if (!$data) {
            return [];
        }

        if (is_string($data)) {
            return explode(',', $data);
        }

        $result = [];

        if ($data) {
            foreach ($data as $key => $value) {
                if (is_array($value) && count($value)) {
                    foreach (static::normalize($value) as $item) {
                        $result[] = $key . '.' . $item;
                    }
                } else {
                    $result[] = $key;
                }
            }
        }

        sort($result);
        return $result;
    }

    /**
     * @deprecated
     */
    public static final function normalizeExpand($data): array
    {
        return static::normalize($data);
    }
}