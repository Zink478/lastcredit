<?php

namespace yii2custom\common\components;

use Yii;
use yii\helpers\FileHelper;

class FileComponent extends yii\base\Component
{
    public $root = 'media';

    public function exists()
    {
        $path = $this->path($path);
        return file_exists($path);
    }

    public function load($path)
    {
        $path = $this->path($path);
        return file_exists($path) ? file_get_contents($path) : null;
    }

    public function save($path, $data)
    {
        $path = $this->path($path);
        $dir = dirname($path);
        !is_dir($dir) && static::createDir($dir);

        if (str_starts_with($data, 'data:')) {
            [, $data] = explode(';', substr($data, strlen('data:')));
            [, $data] = explode(',', $data);
            $data = base64_decode($data);
        }

        return boolval(static::saveFile($path, $data));
    }

    public function delete()
    {
        $path = $this->path($path);
        file_exists($path) && unlink($path);
    }

    public function path($path)
    {
        $dir = dirname(Yii::getAlias('@app')) . '/' . $this->root;
        return Yii::getAlias("$dir/$path");
    }

    protected static function saveFile($path, $data)
    {
        $res = file_put_contents($path, $data);
        chmod($path, 0664);
        chgrp($path, 'www-data');
        return $res;
    }

    protected static function createDir($path)
    {
        if (is_dir($path)) {
            return true;
        }

        $parentDir = dirname($path);
        !is_dir($parentDir) && ($parentDir !== $path) && static::createDir($parentDir);

        return FileHelper::createDirectory($path, 0775, false)
            && chgrp($path, 'www-data');
    }
}