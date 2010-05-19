<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Document implements ArrayAccess, Countable {
	protected $data = array();
	protected $newdata = array();
	protected $unsetdata = array();
	public $collection = null;
	protected $reference = null;
	public $parent = false;

	public function __construct($data=array(), $parent=false, $collection=null) {
		if (!is_array($data))
			throw new Artera_Mongo_Exception('Invalid data provided to the document. $data is not an array.');

		if (is_null($collection))
			$this->collection = Artera_Mongo::documentCollection(get_class($this));
		elseif ($collection instanceof Artera_Mongo_Collection)
			$this->collection = $collection;
		else
			$this->collection = Artera_Mongo::defaultDB()->selectCollection($collection);

		$this->data = $data;
		foreach ($this->data as $key => $data) {
			$this->data[$key] = Artera_Mongo::documentOrSet($data, $this->collection->getName().".$key");
			if ($this->data[$key] instanceof Artera_Mongo_Document || $this->data[$key] instanceof Artera_Mongo_Document_Set)
				$this->data[$key]->parent = $this;
		}
		if ($parent !== false && !$parent instanceof Artera_Mongo_Document && !$parent instanceof Artera_Mongo_Document_Set)
			throw new Artera_Mongo_Exception('Invalid parent. Parent must be one of false, Artera_Mongo_Document or Artera_Mongo_Document_Set');
		$this->parent = $parent;
	}

	public function parentDocument() {
		$parent = $this->parent;
		while (!($parent instanceof Artera_Mongo_Document))
			$parent = $parent->parent;
		return $parent;
	}

	public static function find($query=array(), $fields=array()) {
		if ($query instanceof MongoId) $query = array('_id' => $query);
		$coll = Artera_Mongo::documentCollection(get_called_class());
		return $coll->find($query, $fields);
	}

	public static function findOne($query=array(), $fields=array()) {
		if ($query instanceof MongoId) $query = array('_id' => $query);
		$coll = Artera_Mongo::documentCollection(get_called_class());
		return $coll->findOne($query, $fields);
	}

	public function reference() {
		if (!is_null($this->reference))
			return $this->reference;
		return $this->collection->createDBRef($this->data(false));
	}

	public function isReference() {
		return !is_null($this->reference);
	}

	public function setReference($reference) {
		$this->reference = $reference;
	}

	public function __get($name) {
		$value = null;
		if (array_key_exists($name, $this->newdata))
			$value = $this->newdata[$name];
		elseif (array_key_exists($name, $this->data) && !in_array($name, $this->unsetdata))
			$value = $this->data[$name];
		//Resolve reference
		if (MongoDBRef::isRef($value)) {
			$doc = $this->collection->getDBRef($value);
			$doc->parent = $this;
			return $doc;
		} else
			return $value;
	}

	public function __set($name, $value) {
		if (is_null($value)) {
			if (array_key_exists($name, $this->data)) {
				$this->unsetdata[] = $name;
				if (array_key_exists($name, $this->newdata))
					unset($this->newdata[$name]);
			}
		} else {
			$this->newdata[$name] = Artera_Mongo::documentOrSet($value, $this->collection->getName().".$name");
			if ($this->newdata[$name] instanceof Artera_Mongo_Document || $this->newdata[$name] instanceof Artera_Mongo_Document_Set)
				$this->newdata[$name]->parent = $this;
		}
	}

	public function count() { return count($this->data(false)); }
	public function offsetSet($offset, $value) { return $this->__set($offset, $value); }
	public function offsetExists($offset) { return array_key_exists($name, $this->newdata) || (array_key_exists($name, $this->data) && !in_array($name, $this->unsetdata)); }
	public function offsetUnset($offset) { $this->__set($offset, null); }
	public function offsetGet($offset) { return $this->__get($offset); }

	public function modified() {
		return count($this->newdata) || count($this->unsetdata);
	}

	public function data($translate = true) {
		$data = array_merge($this->data, $this->newdata);
		foreach ($this->unsetdata as $name)
			unset($data[$name]);
		if ($translate)
			foreach ($data as $i => $v) {
				if ($v instanceof Artera_Mongo_Document_Set) {
					$v = $v->savedata(true);
				} elseif ($v instanceof Artera_Mongo_Document) {
					$v = $v->savedata();
				}
				$data[$i] = $v;
			}
		return $data;
	}

	public function savedata() {
		if (is_null($this->reference))
			return $this->data();
		else
			return $this->reference;
	}

	public function save() {
		if (!$this->isReference() && $this->parent && !array_key_exists('_id', $this->data)) {
			$root = $this;
			while ($root->parent !== false)
				$root = $root->parent;
			if ($root instanceof Artera_Mongo_Document_Set)
				throw new Artera_Mongo_Exception('Invalid Document_Set. A Document_Set must have a parent.');
			return $root->save();
		}

		Artera_Mongo::checkConnection();

		$data = $this->data(false);

		if (array_key_exists('_id', $this->data)) {
			$update = array();
			if (count($this->newdata)) {
				$update['$set'] = array();
				foreach ($this->newdata as $field => $newdata) {
					if ($newdata instanceof Artera_Mongo_Document_Set || $newdata instanceof Artera_Mongo_Document)
						$newdata = $newdata->savedata();
					$update['$set'][$field] = $newdata;
				}
			}
			foreach ($data as $field => $olddata) {
				if (!isset($update['$set'][$field])) {
					if ($olddata instanceof Artera_Mongo_Document_Set) {
						$olddata = $olddata->savedata();
						if (!is_null($olddata))
							$update['$set'][$field] = $olddata;
					} elseif ($olddata instanceof Artera_Mongo_Document && $olddata->modified() && !$olddata->isReference()) {
						$update['$set'][$field] = $olddata->savedata();
					}
				}
			}
			if (count($this->unsetdata)) {
				$update['$unset'] = array();
				foreach($this->unsetdata as $name)
					$update['$unset'][$name] = 1;
			}
			print_r($update);
			$this->collection->update( array('_id' => $this->_id), $update );
		} else {
			$insdata = $this->data();
			$this->collection->insert($insdata);
			$data['_id'] = $insdata['_id'];
		}

		$this->data = $data;
		$this->newdata = array();
		$this->unsetdata = array();
	}
}