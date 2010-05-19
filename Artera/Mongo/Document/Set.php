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
	protected $modified = false;
	protected $root = null;
	public $parent = false;

	public function __construct($elements=array(), $parentPath) {
		$this->elements = $elements;
		$this->parentPath = $parentPath;
		foreach ($this->elements as &$el) {
			if (!MongoDBRef::isRef($el)) {
				$el = Artera_Mongo::documentOrSet($el, "{$this->parentPath}.\$");
				if ($el instanceof Artera_Mongo_Document || $el instanceof Artera_Mongo_Document_Set)
					$el->parent = $this;
			}
		}
	}

	public function parentDocument() {
		$parent = $this->parent;
		while ($parent !== false && $parent != Artera_Mongo_Document)
			$parent = $parent->parent();
	}

	protected function rootCollection() {
		if (is_null($this->root)) {
			$this->root = $this;
			while ($this->root->parent != false)
				$this->root = $this->root->parent;
		}
		return $this->root->collection;
	}

	public function count() {
		return count($this->elements);
	}

	public function offsetSet($offset, $value) {
		$this->modified = true;
		$value = Artera_Mongo::documentOrSet($value, "{$this->parentPath}.\$");
		if ($value instanceof Artera_Mongo_Document || $value instanceof Artera_Mongo_Document_Set)
			$value->parent = $this;
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
			$doc = $this->rootCollection()->getDBRef($value);
			$doc->parent = $this;
			$this->elements[$offset] = $doc;
			return $doc;
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