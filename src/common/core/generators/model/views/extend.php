<?php
/**
 * This is the template for generating the extend model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\model\Generator */
/* @var $tableName string full table name */
/* @var $className string class name */

echo "<?php\n";
?>

namespace <?= $generator->ns ?>;

/**
 * This is the model class for table "<?= $generator->generateTableName($tableName) ?>".
 */
class <?= $className ?> extends <?= 'base\\Base' . $className . "\n" ?>
{
}
