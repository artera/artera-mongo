<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Collection {
	protected $collection;

	public function __construct($collection) {
		$this->collection = $collection;
	}

	public function __get($name) {
		if (property_exists($this->collection, $name))
			$ret = $this->collection->$name;
		else
			$ret = $this->collection->__get($name);
		return Artera_Mongo::bind($this, $ret);
	}

	public function __call($name, $arguments) {
		if (method_exists($this->collection, $name))
			return Artera_Mongo::bind($this, call_user_func_array(array($this->collection, $name), $arguments));
	}

	public function __toString() {
		return (string)$this->collection;
	}

	public function findOne($query=array(), $fields=array()) {
		if (is_string($query)) $query = new MongoId($query);
		if ($query instanceof MongoId) $query = array('_id' => $query);
		$data = $this->collection->findOne($query, $fields);
		if (is_null($data)) return null;
		return Artera_Mongo::createDocument($data, $this->collection->getName());
	}

	public function getDBRef($ref) {
		$doc = Artera_Mongo::createDocument($this->collection->getDBRef($ref), $ref['$ref']);
		$doc->setReference($ref);
		return $doc;
	}
}