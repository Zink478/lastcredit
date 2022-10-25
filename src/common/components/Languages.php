<?php

namespace yii2custom\common\components;

use common\models\I18nLanguage;use Yii;use yii\helpers\ArrayHelper;use yii\web\Cookie;

class Languages
{
    const DEFAULT = 'en';
    protected $enabled = true;

    public function default()
    {
        return static::DEFAULT;
    }

    public function enabled($value = null)
    {
        if (!is_null($value)) {
            $this->enabled = $value;
        }

        if (!is_null($this->enabled) && !$this->enabled) {
            return false;
        }

        static $result = null;

        if ($result === null) {
            if (Yii::$app->db->getTableSchema('i18n_language') !== null) {
                $sql = 'select exists (select 1 from i18n_language where slug != \'en\')';
                $result = Yii::$app->db->createCommand($sql)->queryScalar();
            } else {
                $result = false;
            }
        }

        return $result;
    }

    public function list($withDefault = false)
    {
        static $result = null;

        if (is_null($result)) {
            $languages = array_column(self::models(true), 'slug');
            $result = array_combine($languages, $languages);
        }

        if (!$withDefault) {
            return array_diff_key($result, [static::default() => true]);
        }

        return $result;
    }

    /**
    * @param bool $withDefault
    * @return I18nLanguage[]
    */
    public function models($withDefault = false)
    {
        static $result = null;

        if (is_null($result)) {
            $result = I18nLanguage::find()
                ->indexBy('slug')
                ->orderBy(['id' => SORT_ASC])
                ->all();
        }

        if (!$withDefault) {
            return array_diff_key($result, [static::default() => true]);
        }

        return $result;
    }


    public function currentModel()
    {
        static $result = null;

        if (is_null($result)) {
            $result = I18nLanguage::findOne(['slug' => \Yii::$app->language]);
        }

        return $result;
    }

    public function categories($combine = false)
    {
        $categories = ['app', 'web'];

//        static $categories = null;
//
//        if (is_null($categories)) {
//            $sql = 'select category from i18n_source_message group by category order by category asc';
//            $categories = Yii::$app->db->createCommand($sql)->queryColumn();
//        }

        if ($combine) {
            return array_combine($categories, $categories);
        }

        return $categories;
    }

    public function areaBegin($lang, $autoHide = false)
    {
        return ob(function() use($lang, $autoHide) { ?>
            <div class="lang-area <?= $lang ?>"<?= ($autoHide && $lang != 'en') ? ' style="display: none"' : '' ?>>
            <?= $this->flag($lang) ?>
        <?php });
    }

    public function areaEnd()
    {
        return '</div>';
    }

    public function flag($lang)
    {
        $flags = ArrayHelper::getColumn(Yii::$app->languages->models(true), 'image');
        return ob(function() use($lang, $flags) { ?>
            <span class="lang-flag <?= $lang ?>" style="background-image: url(<?= Yii::getAlias('@media-web') . $flags[$lang] ?>)"></span>
        <?php });
    }

    public function detect()
    {
        $languages = $this->list(true);
        $language = Yii::$app->request->get('lang')
            ?: Yii::$app->session->get('language')
                ?: (Yii::$app->request->cookies->get('language')->value ?? null)
                    ?: (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0,
                        2) : null);

        if (!$language || !in_array($language, $languages)) {
            $language = Yii::$app->language;
        }

        Yii::$app->language = $language;
        Yii::$app->session->set('language', $language);
        Yii::$app->response->cookies->add(new Cookie([
            'name' => 'language',
            'value' => $language
        ]));
    }
}