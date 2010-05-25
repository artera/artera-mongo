<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 */
/**
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Cursor implements OuterIterator, Countable {
	protected $cursor;
	public $collection;

	public function __construct($collection, $cursor) {
		$this->collection = $collection;
		$this->cursor = $cursor;
	}

	/**
	 * Get the inner iterator
	 *
	 * @return MongoCursor
	 */
	public function getInnerIterator() {
		return $this->cursor;
	}

	/**
	 * Get the current value
	 *
	 * @return mixed
	 */
	public function current() {
		$current = $this->getInnerIterator()->current();
		if (!is_null($current))
			$current = Artera_Mongo::createDocument($current, $this->collection->getName());
		return $current;
	}

	public function key() {
		return $this->getInnerIterator()->key();
	}

	public function next() {
		return $this->getInnerIterator()->next();
	}

	public function rewind() {
		return $this->getInnerIterator()->rewind();
	}

	public function valid() {
		return $this->getInnerIterator()->valid();
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
		else
			throw new Artera_Mongo_Exception("No such method in MongoCursor: $name");
	}

	public function getNext() {
		$next = $this->cursor->getNext();
		if (!is_null($next))
			$next = Artera_Mongo::createDocument($next, $this->collection->getName());
		return $next;
	}

	public function count($all = FALSE) {
		return $this->cursor->count($all);
	}
}