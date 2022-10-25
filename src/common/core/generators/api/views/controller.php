<?php

/* @var $this yii\web\View */
/* @var $generator \yii2custom\common\core\generators\api\Generator */

echo "<?php\n";
?>

namespace <?= ns($generator->controllerClass) ?>;

use <?= $generator->baseControllerClass ?>;

/**
* <?= cl($generator->controllerClass) ?> implements the CRUD actions for <?= cl($generator->modelClass) ?> model.
*/
class <?= cl($generator->controllerClass) ?> extends <?= cl($generator->baseControllerClass) ?>

{
}