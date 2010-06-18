<?php
/**
 * This class represents a Mongo Document. Any custom Document should extend this class.
 * Since the {@link __construct contructor} is used internally by Artera_Mongo you should not override it.
 * If you need to run some code on a Document initialization you should override {@link initialize}
 * instead and if you really need to override the {@link __construct constructor} you should not modify its signature.
 *
 * You can set any field you want on a Document at runtime without having to define it first.
 * There are different methods you can use to do so:
 * <code>
 * <?php
 * $mydocument->name = 'Simpler method';
 * $mydocument->__set('problematic field name!', 'No problem');
 * $mydocument['description'] = 'This works too';
 * ?>
 * </code>
 *
 * By extending {@link Artera_Events} Artera_Mongo_Document supports event dispatching.
 * There are several events that are fired by Artera_Mongo_Document:
 * <ul>
 *   <li>pre-set ($fieldname, $oldvalue, &$newvalue)</li>
 *   <li>pre-set-$fieldname ($fieldname, $oldvalue, &$newvalue)</li>
 *   <li>pre-save ($document)</li>
 *   <li>pre-insert ($document)</li>
 *   <li>pre-update ($document)</li>
 *   <li>post-save ($document)</li>
 *   <li>post-insert ($document)</li>
 *   <li>post-update ($document)</li>
 * </ul>
 *
 * <code>
 * <?php
 * class BlogPost extends Artera_Mongo_Document {
 *   public function initialize() {
 *     $this->addEvent('pre-set-title', array(&$this, 'cleanTitle'));
 *   }
 *
 *   protected function cleanTitle($name, $oldvalue, &$newvalue) {
 *     if (empty($newvalue))
 *       $newvalue = $oldvalue;
 *     else
 *       $newvalue = ucfirst(trim($newvalue));
 *   }
 * }
 *
 * $post = new BlogPost;
 * $post->title = '  new post ';
 * echo $post->title; //New post
 * ?>
 * </code>
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo_Document extends Artera_Events implements ArrayAccess, Iterator, Countable {
	/**#@+
	 * @access private
	 */
	protected $_data = array();
	protected $_newdata = array();
	protected $_unsetdata = array();
	protected $_reference = null;
	protected $_parent = null;
	protected $_collection = null;
	/**#@-*/

	public function __construct($data=array(), $parent=null, $collection=null) {
		if (!is_array($data))
			throw new Artera_Mongo_Exception('Invalid data provided to the document. $data is not an array.');
		if (is_null($this->collection) || !is_null($collection))
			$this->setCollection($collection)->setData($data, true)->setParent($parent)->initialize();
	}

	public function initialize() { return $this; }

	public function collection() {
		if (isset($this))
			return Artera_Mongo::defaultDB()->selectCollection($this->_collection);
		else
			return Artera_Mongo::documentCollection(get_called_class());
	}

	public function setCollection($collection=null) {
		if (is_null($collection))
			$this->_collection = Artera_Mongo::documentCollection(get_class($this))->getName();
		elseif ($collection instanceof Artera_Mongo_Collection || $collection instanceof MongoCollection)
			$this->_collection = $collection->getName();
		else
			$this->_collection = $collection;
		return $this;
	}

	/**
	 * Returns defined indexes for the collection mapped to this document.
	 *
	 * @return mixed
	 */
	public static function indexes() {
		return isset(static::$_indexes) ? static::$_indexes : array();
	}

	public function parent() {
		return $this->_parent;
	}

	public function setParent($parent) {
		if (!is_null($parent) && !$parent instanceof Artera_Mongo_Document && !$parent instanceof Artera_Mongo_Document_Set)
			throw new Artera_Mongo_Exception('Invalid parent. Parent must be one of NULL, Artera_Mongo_Document or Artera_Mongo_Document_Set');
		$this->_parent = $parent;
		return $this;
	}

	/**
	 * Returns the parent Artera_Mongo_Document if present, NULL if no parent is found.
	 *
	 * @return Artera_Mongo_Document
	 */
	public function parentDocument() {
		$parent = $this->parent();
		while (!is_null($parent) && !($parent instanceof Artera_Mongo_Document))
			$parent = $parent->parent();
		return $parent;
	}

	/**
	 * Query all elements matching $query from the collection of this document
	 * This method is used to query the collection mapped to this Document and works similarly to its
	 * {@link http://www.php.net/manual/en/mongocollection.find.php MongoCollection} equivalent except that
	 * it has been modified to also accept an id (both as string or {@link http://www.php.net/manual/en/class.mongoid.php MongoId})
	 * or a JS function that will automatically be converted to {@link http://www.php.net/manual/en/class.mongocode.php MongoCode}
	 * if the string starts with 'function' as its first parameter.
	 * <code>
	 * <?php
	 * class BlogPosts extends Artera_Mongo_Document {}
	 * Artera_Mongo::map('posts', 'BlogPosts');
	 * $posts = BlogPosts::find(array('author' => 'Foo Bar'))->limit(10);
	 * $posts = BlogPosts::find('function() { return this.author == 'Foo' || this.author == 'Bar'; }');
	 * ?>
	 * </code>
	 * @param mixed $query The query or a document id
	 * @param mixed $fields An optional subset of fields to retrieve from the collection
	 * @return Artera_Mongo_Cursor
	 */
	public static function find($query=array(), $fields=array()) {
		if (is_string($query)) $query = substr($query,0,8)=='function' ? new MongoCode($query) : new MongoId($query);
		if ($query instanceof MongoId) $query = array('_id' => $query);
		$coll = Artera_Mongo::documentCollection(get_called_class());
		return $coll->find($query, $fields);
	}

	/**
	 * Query one element from the collection of this document
	 * This method is used to query the collection mapped to this Document and works similarly to its
	 * {@link http://www.php.net/manual/en/mongocollection.findOne.php MongoCollection} equivalent except that
	 * it has been modified to also accept an id (both as string or {@link http://www.php.net/manual/en/class.mongoid.php MongoId})
	 * or a JS function that will automatically be converted to {@link http://www.php.net/manual/en/class.mongocode.php MongoCode}
	 * if the string starts with 'function' as its first parameter.
	 * <code>
	 * <?php
	 * class BlogPosts extends Artera_Mongo_Document {}
	 * Artera_Mongo::map('posts', 'BlogPosts');
	 * $post = BlogPosts::findOne($_GET['id']);
	 * ?>
	 * </code>
	 * @param mixed $query The query or a document id
	 * @param mixed $fields An optional subset of fields to retrieve from the collection
	 * @return Artera_Mongo_Document
	 */
	public static function findOne($query=array(), $fields=array()) {
		if (is_string($query)) $query = substr($query,0,8)=='function' ? new MongoCode($query) : new MongoId($query);
		if ($query instanceof MongoId) $query = array('_id' => $query);
		$coll = Artera_Mongo::documentCollection(get_called_class());
		return $coll->findOne($query, $fields);
	}

	/**
	 * Returns a {@link http://www.php.net/manual/en/class.mongodbref.php MongoDBRef reference} to this document.
	 * @return MongoDBRef
	 */
	public function reference() {
		if ($this->isReference())
			return $this->_reference;
		return $this->collection()->createDBRef($this->data(false));
	}

	public function isReference() {
		return !is_null($this->_reference);
	}

	public function setReference($reference) {
		$this->_reference = $reference;
		return $this;
	}

	public function getDBRef($reference) {
		$doc = $this->collection()->getDBRef($reference);
		$doc->setParent($this);
		return $doc;
	}

	public function __isset($name) {
		return array_key_exists($name, $this->_newdata) || (array_key_exists($name, $this->_data) && !in_array($name, $this->_unsetdata));
	}

	public function __get($name) {
		$value = null;
		if (array_key_exists($name, $this->_newdata))
			$value = $this->_newdata[$name];
		elseif (array_key_exists($name, $this->_data) && !in_array($name, $this->_unsetdata))
			$value = $this->_data[$name];
		//Resolve reference
		if (MongoDBRef::isRef($value))
			return $this->getDBRef($value);
		else
			return $value;
	}

	public function __set($name, $value) {
		if (strpos($name, '.') !== false)
			throw new Artera_Mongo_Exception("The '.' character must not appear anywhere in the key name.");
// 			if (strlen($name)>0 && $name[0]=='$')
// 				throw new Artera_Mongo_Exception("The '$' character must not be the first character in the key name.");
		if (!is_null($this->parent())) {
			if ($this->_parent instanceof Artera_Mongo_Document_Set) {
				$eventFieldName = explode('.', $this->_parent->parentPath);
				$eventFieldName = implode('.', array_slice($eventFieldName,1)).".$.$name";
				$pdoc = $this->parentDocument();
				$pdoc->fireEvent("pre-set", array($eventFieldName, $this->__get($name), &$value));
				$pdoc->fireEvent("pre-set-$eventFieldName", array($eventFieldName, $this->__get($name), &$value));
				$pdoc->fireEvent("internal-pre-set", array($eventFieldName, $this->__get($name), &$value));
			}
		}
		$this->fireEvent("pre-set", array($name, $this->__get($name), &$value));
		$this->fireEvent("pre-set-$name", array($name, $this->__get($name), &$value));
		$this->fireEvent("internal-pre-set", array($name, $this->__get($name), &$value));
		if (is_null($value)) {
			if (array_key_exists($name, $this->_data))
				$this->_unsetdata[] = $name;
			if (array_key_exists($name, $this->_newdata))
				unset($this->_newdata[$name]);
		} else {
			$this->_newdata[$name] = $this->translate($name, $value);
		}
		return $this;
	}

	protected function translate($name, $value, $originalData=false) {
		return Artera_Mongo::documentOrSet($value, "{$this->_collection}.$name", $this, $originalData);
	}

	public function setData(array $data, $originalData=false) {
		foreach ($data as $name => $value)
			if ($originalData)
				$this->_data[$name] = $this->translate($name, $value, true);
			else
				$this->__set($name, $value);
		return $this;
	}

	/**
	 * Removes elements matching the query param or the document instance if no query is specified.
	 * This method can be called both statically and dynamically, but the query parameter is only required if it is called statically.
	 * @param mixed $query If no query is specified, delete this document.
	 * @return Artera_Mongo_Document $this
	 */
	public function remove($query=null) {
		if (is_null($query) && !isset($this))
			throw new Artera_Mongo_Exception('The remove method cannot be called statically without parameters. If you really want to remove every document in the collection call Artera_Mongo_Document::remove(array());');

		if (is_null($query)) {
			$this->collection()->remove(array('_id' => $this->_id));
		} else {
			$collection = isset($this) ? $this->collection() : Artera_Mongo::documentCollection(get_called_class());
			$collection->remove($query);
		}

		return $this;
	}

	/**
	 * Returns the number of fields in this document.
	 * @return int
	 */
	public function count() { return count($this->data(false)); }
	public function offsetSet($offset, $value) { return $this->__set($offset, $value); }
	public function offsetExists($offset) { return $this->__isset($offset); }
	public function offsetUnset($offset) { return $this->__set($offset, null); }
	public function offsetGet($offset) { return $this->__get($offset); }

	public function rewind() {
		reset($this->_data);
		reset($this->_newdata);
	}
	public function current() {
		return $this->offsetGet($this->key());
	}
	public function key() {
		$key = key($this->_data);
		return is_null($key) || in_array($key, $this->_unsetdata) ? key($this->_newdata) : $key;
	}
	public function next() {
		if (key($this->_data) !== null)
			return next($this->_data);
		else
			return next($this->_newdata);
	}
	public function valid() {
		return $this->key() !== null;
	}

	public function modified() {
		return count($this->_newdata) || count($this->_unsetdata);
	}

	public function data($translate = true) {
		$data = array_merge($this->_data, $this->_newdata);
		foreach ($this->_unsetdata as $name)
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
		if ($this->isReference())
			return $this->_reference;
		else
			return $this->data();
	}

	/**
	 * Save the document to the mapped collection.
	 */
	public function save() {
		if (!$this->isReference() && !is_null($this->parent()) && !array_key_exists('_id', $this->_data)) {
			$root = $this;
			while (!is_null($root->parent()))
				$root = $root->parent();
			if ($root instanceof Artera_Mongo_Document_Set)
				throw new Artera_Mongo_Exception('Invalid Document_Set. A Document_Set must have a parent.');
			return $root->save();
		}

		Artera_Mongo::checkConnection();

		$isInsert = !isset($this->_id);
		$this->fireEvent('pre-save', array($this));
		$this->fireEvent('pre-'.($isInsert ? 'insert' : 'update'), array($this));

		$data = $this->data(false);

		if ($isInsert) {
			$insdata = $this->data();
			$insdata['_class'] = get_class($this);
			$this->collection()->insert($insdata);
			$data['_id'] = $insdata['_id'];
		} else {
			$update = array();
			if (count($this->_newdata)) {
				$update['$set'] = array();
				foreach ($this->_newdata as $field => $newdata) {
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
			if (count($this->_unsetdata)) {
				$update['$unset'] = array();
				foreach($this->_unsetdata as $name)
					$update['$unset'][$name] = 1;
			}
			if (!empty($update))
				$this->collection()->update( array('_id' => $this->_id), $update );
		}

		$this->_data = $data;
		$this->_newdata = array();
		$this->_unsetdata = array();

		$this->fireEvent('post-save', array($this));
		$this->fireEvent('post-'.($isInsert ? 'insert' : 'update'), array($this));

		return $this;
	}
}