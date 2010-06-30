<?php
/**
 * A Document class that automatically generates a lowercase variant of the specified fields.
 * When extending this class you can specify an array ({@link $_cifields}) of fields that will have an automatically
 * created variant with the content converted in lowercase so that you can sort by that variant
 * to obtain case-insensitive sorts.
 *
 * The variant names are auto-named as "_ci_$fieldname"
 * Example usage:
 * <code>
 * <?php
 * class BlogPost extends Artera_Mongo_Document_CaseInsensitive {
 *   public static $_cifields = array('name');
 * }
 *
 * $post_ci_sorted = BlogPost::find()->limit(10)->sort(array('_ci_name' => 1));
 * ?>
 * </code>
 *
 * An index for every field specifiend in {@link $_cifields} will be automatically created.
 *
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Document_CaseInsensitive extends Artera_Mongo_Document {
	public static $_cifields = array();

	public function __construct($data=array(), $parent=null, $collection=null) {
		parent::__construct($data, $parent, $collection);
		$this->addEvent('internal-pre-set', array(&$this, 'setCiVariant'));
	}

	public static function indexes() {
		$cifields = static::$_cifields;
		foreach ($cifields as &$field)
			$field = "_ci_$field";
		return array_merge(parent::indexes(), $cifields);
	}

	protected function setCiVariant($name, $oldvalue, &$newvalue) {
		if (in_array($name, static::$_cifields)) {
			if (is_string($newvalue)) {
				//$lvalue = strtolower($newvalue);
				//$this->__set("_ci_$name", $newvalue == $lvalue ? null : $lvalue);
				$this->__set("_ci_$name", strtolower($newvalue));
			} else {
				$this->__set("_ci_$name", null);
			}
		}
	}
}