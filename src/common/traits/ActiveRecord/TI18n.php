<?php

namespace yii2custom\common\traits\ActiveRecord;

use common\models\I18nModelMessage;
use Yii;
use yii\validators\RequiredValidator;
use yii\validators\UniqueValidator;
use yii\validators\Validator;
use yii2custom\common\core\ActiveQuery;
use yii2custom\common\core\ActiveRecord;

/**
 * @mixin ActiveRecord
 * @property-read I18nModelMessage[] $i18nModelMessages
 */
trait TI18n
{
    private $i18n = [];

    /**
     * @return array
     */
    public static function i18n()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function attributes()
    {
        return $this->withI18nAttributes(parent::attributes());
    }

    /**
     * @inheritDoc
     */
    public function safeAttributes()
    {
        return $this->withI18nAttributes(parent::safeAttributes());
    }

    /**
     * @param string $attribute
     * @return string[]
     */
    public function i18nAttributes(string $attribute): array
    {
        if (self::enabled() && in_array($attribute, $this->i18n())) {
            $result = [];
            foreach (Yii::$app->languages->list(true) as $language) {
                $result[] = $attribute . '_' . $language;
            }
            return $result;
        }

        return [$attribute];
    }

    /**
     * @inheritDoc
     */
    public function getAttributeLabel($attribute)
    {
        if (self::enabled()) {
            [$i18nAttribute, $language] = $this->parseI18nAttribute($attribute);

            if ($i18nAttribute) {
                return parent::getAttributeLabel($i18nAttribute) . ' ' . $language;
            }
        }

        return parent::getAttributeLabel($attribute);
    }

    /**
     * Set i18n validators
     * @inheritDoc
     */
    public function createValidators()
    {
        /** @var Validator[] $validators */
        $validators = parent::createValidators();
        if (self::enabled()) {
            foreach ($validators as $validator) {
                foreach ($validator->attributes as $key => $attribute) {
                    if (in_array($attribute, $this->i18n())) {
                        foreach (Yii::$app->languages->list() as $lang) {
                            if ($lang == Yii::$app->languages->default()
                                || !($validator instanceof RequiredValidator || $validator instanceof UniqueValidator)) {
                                $validator->attributes[] = $attribute . '_' . $lang;
                            }
                        }
                    }
                }
            }
        }

        return $validators;
    }

    /**
     * @inheritDoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if (self::enabled()) {
            foreach ($this->i18n as $attribute => $value) {
                [$attribute, $lang] = $this->parseI18nAttribute($attribute);

                if (!$value) {
                    Yii::$app->db->createCommand()->delete('i18n_model_message', [
                        'language' => $lang,
                        'model_name' => cl(static::class),
                        'model_id' => $this->getAttribute('id'),
                        'attribute' => $attribute
                    ])->execute();
                } else {
                    Yii::$app->db->createCommand()->upsert('i18n_model_message', [
                        'language' => $lang,
                        'model_name' => cl(static::class),
                        'model_id' => $this->getAttribute('id'),
                        'attribute' => $attribute,
                        'translation' => $value
                    ], [
                        'translation' => $value,
                    ])->execute();
                }
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritDoc
     */
    public function afterDelete()
    {
        if (self::enabled()) {
            foreach ($this->i18nModelMessages as $translation) {
                $translation->delete();
            }
        }

        parent::afterDelete();
    }

    /**
     * @inheritDoc
     */
    public function __set($name, $value)
    {
        [$attribute, $lang] = $this->parseI18nAttribute($name);
        if (!static::isSearchModel() && self::enabled() && $attribute) {
            if ($lang == Yii::$app->languages->default()) {
                parent::__set($attribute, $value);
            } else {
                $this->i18n[$name] = $value;
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * @inheritDoc
     */
    public function __get($name)
    {
        if (static::isSearchModel() || !self::enabled()) {
            return parent::__get($name);
        }

        if (in_array($name, $this->i18n()) && Yii::$app->language != Yii::$app->languages->default()) {
            return static::__get($name . '_' . Yii::$app->language) ?? parent::__get($name);
        }

        [$attribute, $lang] = $this->parseI18nAttribute($name);

        if ($attribute) {
            if ($lang == Yii::$app->languages->default()) {
                return parent::__get($attribute);
            }

            if (isset($this->i18n[$name])) {
                return $this->i18n[$name];
            }

            foreach ($this->i18nModelMessages as $translation) {
                if (
                    $translation->language == $lang
                    && $translation->model_name == cl(static::class)
                    && $translation->attribute == $attribute
                ) {
                    $this->i18n[$name] = $translation->translation;
                    return $translation->translation;
                }
            }

            return null;
        }

        return parent::__get($name);
    }

    /**
     * @inheritDoc
     */
    protected function insertInternal($attributes = null)
    {
        $attributes = $this->withoutI18nAttributes($attributes ?? $this->attributes());
        return parent::insertInternal($attributes);
    }

    /**
     * @inheritDoc
     */
    protected function updateInternal($attributes = null)
    {
        $attributes = $this->withoutI18nAttributes($attributes ?? $this->attributes());
        return parent::updateInternal($attributes);
    }

    /**
     * @return ActiveQuery
     */
    public function getI18nModelMessages()
    {
        if (self::enabled()) {
            /** @var ActiveQuery $query */
            $query = $this->hasMany(I18nModelMessage::class, ['model_id' => 'id']);
            $query->from(['i18n' => I18nModelMessage::tableName()]);
            return $query->andWhere(['i18n.model_name' => cl(static::class)]);
        }

        return [];
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function isI18nAttribute(string $name): bool
    {
        return (bool)$this->parseI18nAttribute($name)[0];
    }

    private function parseI18nAttribute(string $name): array
    {
        if (self::enabled()) {
            if (preg_match('/^([\w\d_]+?)_([a-z]{2})$/', $name, $ms)) {
                array_shift($ms);
                [$attribute, $language] = $ms;
                if (in_array($language, Yii::$app->languages->list(true)) && in_array($attribute, $this->i18n())) {
                    return [$attribute, $language];
                }
            }
        }

        return [null, null];
    }

    private function withI18nAttributes(array $attributes): array
    {
        if (self::enabled()) {
            $result = [];
            foreach ($attributes as $attribute) {
                $result[] = $attribute;
                if (in_array($attribute, $this->i18n())) {
                    foreach (Yii::$app->languages->list(true) as $lang) {
                        $result[] = $attribute . '_' . $lang;
                    }
                }
            }

            return $result;
        }

        return $attributes;
    }

    private function withoutI18nAttributes(array $attributes): array
    {
        if (self::enabled()) {
            foreach ($attributes as $key => $value) {
                if ($this->isI18nAttribute($value)) {
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    private static function enabled()
    {
        return Yii::$app->has('languages') && Yii::$app->languages->enabled();
    }
}
