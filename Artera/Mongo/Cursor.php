<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Cursor implements Countable {
	protected $cursor;
	public $collection;

	public function __construct($collection, $cursor) {
		$this->collection = $collection;
		$this->cursor = $cursor;
	}

	public function __get($name) {
		if (property_exists($this->cursor, $name))
			$ret = $this->cursor->$name;
		else
			$ret = $this->cursor->__get($name);
		return Artera_Mongo::bind($this, $ret);
	}

	public function __call($name, $arguments) {
		if (method_exists($this->cursor, $name))
			return Artera_Mongo::bind($this, call_user_func_array(array($this->cursor, $name), $arguments));
	}

	public function getNext() {
		$next = $this->cursor->getNext();
		return Artera_Mongo::createDocument($next, $this->collection->getName());
	}

	public function count() {
		return $this->cursor->count();
	}
}