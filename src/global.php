<?php

/** Global functions */

/**
 * @param $mixed
 * @return void
 */
function ex($data)
{
    empty($_SERVER['SHELL']) && header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    exit;
}

/**
 * @param $class string|string[]
 * @param $suffix string|string[]|null
 * @return string
 */
function ns($class, $suffix = null)
{
    $class = (array)(is_object($class) ? get_class($class) : $class);
    $result = explode('\\', join('\\', $class));
    array_pop($result);
    $result = join('\\', $result);

    if ($suffix) {
        $suffix = join('\\', (array)$suffix);
        if (str_ends_with($result, $suffix)) {
            $result = rtrim(substr_replace($result, '', -strlen($suffix)), '\\');
        }
    }

    return $result;
}

/**
 * @param $class string|string[]
 * @param $suffix string|null
 * @return strings
 */
function cl($class, $suffix = null)
{
    $class = (array)(is_object($class) ? get_class($class) : $class);
    $result = explode('\\', join('\\', $class));
    $result = array_pop($result);

    if ($suffix) {
        $suffix = join('\\', (array)$suffix);
        if (str_ends_with($result, $suffix)) {
            $result = substr_replace($result, '', -strlen($suffix));
        }
    }

    return $result;
}

/**
 * @param $class string|string[]
 * @param $suffix string|null
 * @param $section string|null
 * @return string
 */
function lc($class, $suffix = null, $section = null)
{
    $section = $section ?? preg_replace('/^app\-/', '', Yii::$app->id);
    $ns = preg_replace('/^common\\\\/', $section . '\\', ns($class));
    $local = $ns . '\\' . cl($class, $suffix);

    return class_exists($local) ? $local : $class;
}

/**
 * @param callable $callback
 * @return false|string
 */
function ob(callable $callback)
{
    ob_start();
    $callback();
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}