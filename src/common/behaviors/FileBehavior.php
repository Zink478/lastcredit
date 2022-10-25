<?php

namespace yii2custom\common\behaviors;

use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeBehavior;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\web\UploadedFile;
use yii2custom\common\core\ActiveRecord;
use yii2custom\common\helpers\RuleHelper;

class FileBehavior extends AttributeBehavior
{
    /** @var ActiveRecord */
    public $owner;

    /** @var string|bool|null */
    public $suffix = false;
    public $attribute = 'file';
    public $nameAttribute = 'id';
    public $name = '';
    public $path = '';

    protected $current = [];
    protected $swapped = [];
    protected $completed = false;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (!$this->attribute) {
            throw new InvalidConfigException('"attribute" property is required.');
        }

        if (!$this->name && !$this->nameAttribute) {
            throw new InvalidConfigException('"name" or "nameAttribute" property is required.');
        }

        if ($this->suffix === true) {
            $this->suffix = '-' . $this->attribute;
        }

        if (strlen($this->suffix) && !preg_match('/^[\w\d-]+$/', $this->suffix)) {
            throw new InvalidConfigException('"suffix" may contain only latin characters, numbers and hyphen');
        }
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'find',
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'prepare',
            ActiveRecord::EVENT_AFTER_INSERT => 'save',
            ActiveRecord::EVENT_AFTER_UPDATE => 'save',
            ActiveRecord::EVENT_AFTER_DELETE => 'delete',
        ];
    }

    public function find()
    {
        $this->owner->{$this->attribute} = $this->multiple() ? $this->files() : $this->file();
    }

    public function prepare()
    {
        if ($this->ignore()) {
            return;
        }

        $this->current = $this->multiple()
            ? array_filter(explode(',', $this->owner->{$this->attribute}[0] ?? ''))
            : [$this->owner->{$this->attribute}];

        $this->owner->{$this->attribute} = $this->multiple()
            ? UploadedFile::getInstances($this->owner, $this->attribute)
            : UploadedFile::getInstance($this->owner, $this->attribute);
    }

    public function save()
    {
        if ($this->ignore()) {
            return;
        }

        /** @var UploadedFile $file */
        $path = $this->path(true);
        $this->clear($this->current);

        if ($this->multiple()) {
            $files = $this->files();
            $this->sort($files);

            if ($this->owner->{$this->attribute}) {
                $offset = $files ? array_key_last($files) : 0;
                foreach ($this->owner->{$this->attribute} as $key => $file) {
                    $file->saveAs($path . '/' . ($key + $offset + 1) . $this->suffix . '.' . $file->extension);
                }
            }
        } else {
            $file = $this->owner->{$this->attribute};
            if ($file instanceof UploadedFile) {
                $file->saveAs($path . '/' . $this->name() . $this->suffix . '.' . $file->extension);
            }
        }

        $this->find();
        $this->completed = true;
    }

    public function delete()
    {
        $this->clear();
    }

    /** Protected */

    protected function sort($files)
    {
        $exists = [];
        foreach ($files as $key => $file) {
            if (in_array($key, $this->current)) {
                $exists[] = $key;
            }
        }

        foreach ($exists as $i => $key) {
            $index = array_search($key, $this->current);
            if ($i != $index) {
                $this->swap($key, $this->current[$i]);
            }
        }
    }

    protected function swap(int $from, int $to)
    {
        if (in_array($from, $this->swapped) || in_array($to, $this->swapped)) {
            return;
        }

        $path = $this->path(true);
        $fromFile = null;
        $toFile = null;

        foreach (glob($path . '/*') as $fn) {
            $key = pathinfo($fn, PATHINFO_FILENAME);
            if ($key == $from) {
                $fromFile = $fn;
            } else if ($key == $to) {
                $toFile = $fn;
            }
            if ($fromFile && $toFile) {
                break;
            }
        }

        $fromInfo = pathinfo($fromFile);
        $toInfo = pathinfo($toFile);

        rename($fromFile, $fromFile . '.tmp');
        rename($toFile, $toFile . '.tmp');

        rename($fromFile . '.tmp', $toInfo['dirname'] . '/' . $toInfo['filename'] . '.' . $fromInfo['extension']);
        rename($toFile . '.tmp', $fromInfo['dirname'] . '/' . $fromInfo['filename'] . '.' . $toInfo['extension']);

        $this->swapped[] = $from;
        $this->swapped[] = $to;
    }

    protected function multiple()
    {
        $rule = RuleHelper::get($this->owner->rules(), $this->attribute, 'image')
            ?? RuleHelper::get($this->owner->rules(), $this->attribute, 'file');

        return (bool)($rule['maxFiles'] ?? false);
    }

    protected function path($create = false)
    {
        $path = $this->path ? $this->path : Inflector::camel2id(cl($this->owner));
        $path = Yii::getAlias('@media/' . $path);

        if ($this->multiple()) {
            $path .= '/' . $this->name();
        }

        if ($create && !file_exists($path)) {
            FileHelper::createDirectory($path, 0777, true);
        }

        return $path;
    }

    protected function file()
    {
        foreach (glob($this->path() . '/' . $this->name() . '*') as $fn) {
            if (($this->name() . $this->suffix) == pathinfo($fn, PATHINFO_FILENAME)) {
                $size = filesize($fn);
                return substr($fn, strlen(Yii::getAlias('@media'))) . '?' . $size;
            }
        }

        return null;
    }

    protected function files()
    {
        $result = [];

        if (!$this->owner->isNewRecord) {
            $path = $this->path();
            if (file_exists($path)) {
                foreach (glob($path . '/*') as $fn) {
                    $ext = pathinfo($fn, PATHINFO_EXTENSION);
                    if ($ext != 'tmp') {
                        $key = pathinfo($fn, PATHINFO_FILENAME);
                        $result[$key] = substr($fn, strlen(Yii::getAlias('@media')));
                    }
                }
            }
        }
        ksort($result);
        return $result;
    }

    protected function clear($exclude = [])
    {
        $files = array_filter($this->multiple() ? $this->files() : [$this->file()]);

        foreach ($files as $key => $file) {
            $file = Yii::getAlias('@media') . $file;
            if (!in_array(pathinfo($file, PATHINFO_FILENAME), $exclude)) {
                $file = preg_replace('/\?.+$/', '', $file);
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function name()
    {
        return $this->name ?: $this->owner->{$this->nameAttribute};
    }

    protected function ignore()
    {
        return (
            (Yii::$app instanceof yii\console\Application)
            || !Yii::$app->request->isPost
            || $this->owner::isSearchModel()
            || $this->completed
        );
    }
}
