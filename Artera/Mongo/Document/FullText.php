<?php
/**
 * A Document class that automatically generates a keyword list from all the words contained in the specifield fields.
 * When extending this class you can specify an array ({@link $_ftfields}) of fields that will be parsed for keywords.
 * The keyword field of the document will be named _keywords.
 *
 * Example usage:
 * <code>
 * <?php
 * class BlogPost extends Artera_Mongo_Document_FullText {
 *   public static $_ftfields = array('title', 'content');
 * }
 *
 * // Find all posts that mention "food"
 * $posts = BlogPost::find(array('keywords' => 'food'));
 * ?>
 * </code>
 *
 * An index for the _keywords field will be automatically created.
 *
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Document_FullText extends Artera_Mongo_Document_CaseInsensitive {
	public static $_ftfields = array();
	//protected static $_wordsSplitter = '/[\\s\\.\\:,\\/\\\\_!?]+/';
	protected static $_wordsSplitter = '/\\W+/';

	public function __construct($data=array(), $parent=null, $collection=null) {
		parent::__construct($data, $parent, $collection);
		$this->addEvent('pre-save', array(&$this, 'checkKeywords'));
	}

	public static function indexes() {
		$indexes = parent::indexes();
		$indexes[] = '_keywords';
		return $indexes;
	}

	protected function keywordsFilter($keyword) {
		if (strlen($keyword)<3) return null;
		return strtolower($keyword);
	}

	protected function parseWords() {
		$keywords = array();
		foreach (static::$_ftfields as $field) {
			if (isset($this->$field) && is_string($this->$field)) {
				$text = $this->$field;
				$this->fireEvent("fulltext-filter", array($field, &$text));
				foreach (preg_split(static::$_wordsSplitter, $text) as $word) {
					$keyword = $this->keywordsFilter($word);
					if (!empty($keyword))
						$keywords[] = $keyword;
				}
			}
		}
		//print_r(array_unique($keywords)); exit;
		$this->_keywords = array_unique($keywords);
	}

	protected function checkKeywords($document) {
		$rebuild = false;
		foreach (static::$_ftfields as $field)
			if (array_key_exists($field, $this->_newdata) || in_array($field, $this->_unsetdata))
				$rebuild = true;

		if ($rebuild)
			$this->parseWords();
	}
}