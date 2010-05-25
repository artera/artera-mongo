<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Document_Set implements ArrayAccess, Iterator, Countable {
	protected $elements = array();
	protected $parentPath = null;
	protected $elementPath = null;
	protected $modified = false;
	protected $root = null;
	protected $_parent = null;

	public function __construct($elements=array(), $parentPath) {
		$this->parentPath = $parentPath;
		$pathElements = explode('.', $parentPath, 2);
		$this->elementPath = $pathElements[1].'.$';
		$this->setData($elements, true);
		$this->modified = false;
	}

	public function parent() {
		return $this->_parent;
	}

	public function setParent($parent) {
		if (!is_null($parent) && !$parent instanceof Artera_Mongo_Document && !$parent instanceof Artera_Mongo_Document_Set)
			throw new Artera_Mongo_Exception('Invalid parent. Parent must be one of NULL, Artera_Mongo_Document or Artera_Mongo_Document_Set');
		$this->_parent = $parent;
	}

	/**
	 * Returns the parent Artera_Mongo_Document if present, NULL if not parent is found
	 *
	 * @return Artera_Mongo_Document
	 */
	public function parentDocument() {
		$parent = $this->parent();
		while (!is_null($parent) && !($parent instanceof Artera_Mongo_Document))
			$parent = $parent->parent();
		return $parent;
	}

	public function rootDocument() {
		if (is_null($this->root)) {
			$this->root = $this;
			while (!is_null($this->root->parent()))
				$this->root = $this->root->parent();
		}
		return $this->root;
	}

	public function rootCollection() {
		return $this->rootDocument()->collection;
	}

	public function getDBRef($reference) {
		$doc = $this->rootCollection()->getDBRef($reference);
		$doc->setParent($this);
		return $doc;
	}

	public function count() {
		return count($this->elements);
	}

	protected function translate($value, $originalData=false) {
		return Artera_Mongo::documentOrSet($value, "{$this->parentPath}.\$", $this, $originalData);
	}

	public function setData(array $data, $originalData=false) {
		foreach ($data as $value)
			if ($originalData)
				$this->elements[] = $this->translate($value, true);
			else
				$this->offsetSet(null, $value);
		return $this;
	}

	public function offsetSet($offset, $value) {
		$pdoc = $this->parentDocument();
		if (!is_null($pdoc))
			$pdoc->fireEvent("pre-set-{$this->elementPath}", array($this->elementPath, $this->offsetGet($offset), &$value));

		$this->modified = true;
		$value = $this->translate($value);
		if (is_null($offset))
			$this->elements[] = $value;
		else
			$this->elements[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->elements[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->elements[$offset]);
		$this->modified = true;
	}

	public function offsetGet($offset) {
		if (!isset($this->elements[$offset])) return null;

		$value = $this->elements[$offset];
		//Resolve reference
		if (MongoDBRef::isRef($value)) {
			$this->elements[$offset] = $this->getDBRef($value);
			return $this->elements[$offset];
		} else return $value;
	}

	public function rewind() { reset($this->elements); }
	public function current() { return $this->offsetGet($this->key()); }
	public function key() { return key($this->elements); }
	public function next() { return next($this->elements); }
	public function valid() { return $this->key() !== null; }

	public function elements() {
		return $this->elements;
	}

	public function modified() {
		return $this->modified;
	}

	public function savedata($force = false) {
		$modified = $this->modified;
		$elements = $this->elements();
		if (!$force && !$modified) {
			foreach ($elements as $offset => $value) {
				if (($value instanceof Artera_Mongo_Document_Set || $value instanceof Artera_Mongo_Document) && $value->modified())
					$modified = true;
			}
		}
		if ($force || $modified) {
			foreach ($elements as $offset => $value) {
				if ($value instanceof Artera_Mongo_Document_Set)
					$value = $value->savedata($force);
				elseif ($value instanceof Artera_Mongo_Document)
					$value = $value->data();
				$elements[$offset] = $value;
			}
			return $elements;
		} else {
			return null;
		}
	}
}