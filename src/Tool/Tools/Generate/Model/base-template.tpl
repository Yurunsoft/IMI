<?= '<?php' ?>

namespace <?= $namespace ?>\Base;

use Imi\Model\Model;
use Imi\Model\Annotation\Column;

/**
 * <?= $className ?>Base

<?php foreach($fields as $field):?>
 * @property <?= $field['phpType'] ?> $<?= $field['varName'] ?>

<?php endforeach;?>
 */
abstract class <?= $className ?>Base extends Model
{
<?php
	foreach($fields as $field):
?>
	/**
	 * <?= $field['name'] ?><?= '' === $field['comment'] ? '' : (' - ' . $field['comment']) ?>

	 * @Column(name="<?= $field['name'] ?>", type="<?= $field['type'] ?>", length=<?= $field['length'] ?>, accuracy=<?= $field['accuracy'] ?>, nullable=<?= json_encode($field['nullable']) ?>, default="<?= $field['default'] ?>", isPrimaryKey=<?= json_encode($field['isPrimaryKey']) ?>, primaryKeyIndex=<?= $field['primaryKeyIndex'] ?>, isAutoIncrement=<?= json_encode($field['isAutoIncrement']) ?>)
	 * @var <?= $field['phpType'] ?>

	 */
	protected $<?= $field['varName'] ?>;

	/**
	 * 获取 <?= $field['varName'] ?><?= '' === $field['comment'] ? '' : (' - ' . $field['comment']) ?>

	 *
	 * @return <?= $field['phpType'] ?>

	 */ 
	public function get<?= ucfirst($field['varName']) ?>()
	{
		return $this-><?= $field['varName'] ?>;
	}

	/**
	 * 赋值 <?= $field['varName'] ?><?= '' === $field['comment'] ? '' : (' - ' . $field['comment']) ?>

	 * @param <?= $field['phpType'] ?> $<?= $field['varName'] ?> <?= $field['name'] ?>

	 * @return static
	 */ 
	public function set<?= ucfirst($field['varName']) ?>($<?= $field['varName'] ?>)
	{
		$this-><?= $field['varName'] ?> = $<?= $field['varName'] ?>;
		return $this;
	}

<?php
	endforeach;
?>
}
