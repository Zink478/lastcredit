<?php
/**
 * This is the template for generating the extend model class of a specified table.
 */

/* @var $this yii\web\View */
/* @var $generator \yii2custom\common\core\generators\api\Generator */
/* @var $fields string[] */

$ns = ns($generator->modelClass);
$modelClass = cl($generator->modelClass);
$commonClass = $generator->baseNs . '\\' . $modelClass;
$tableName = $commonClass::tableName();

echo "<?php\n";
?>

namespace <?= $ns ?>;

/**
 * This is the model class for table "<?= $tableName ?>".
 */
class <?= $modelClass?> extends \<?= $commonClass . "\n" ?>
{
    public function fields()
    {
        return [<?= "\n            " . join(",\n            ", $fields) . "\n        " ?>];
    }
<?php if ($generator->generateSchema): ?>
    public function schema()
    {
        return array_merge_recursive(parent::schema(), [
            'grid' => [<?= "\n                " . join(",\n                ", $fields) . "\n            " ?>],
            'form' => [<?= "\n                " . join(",\n                ", $fields) . "\n            " ?>]
        ]);
    }
<?php endif ?>
}
