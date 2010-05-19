<?php
/**
 * @category   Artera
 * @package    Artera_Mongo
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Mongo extends Mongo {
	public static $_defaultDB;
	protected static $_connection = null;
	protected static $_map = array();

	public function __construct($server='mongodb://localhost:27017', $options=array('connect' => false)) {
		if ($options instanceof Zend_Config)
			$options = $options->toArray();
		parent::__construct($server, $options);
		self::$_connection = $this;
		if (preg_match('|/([a-zA-Z][a-zA-Z0-9]*)$|', $server, $matches))
			$this->setDefaultDB($matches[1]);
	}

	public static function defaultDB() {
		if (is_null(self::$_connection))
			throw new Artera_Mongo_Exception;
		else
			return self::$_connection->selectDB(self::$_defaultDB);
	}

	public static function setDefaultDB($db) {
		self::$_defaultDB = $db;
	}

	public static function connection() {
		return self::$_connection;
	}

	public static function checkConnection() {
		if (!self::$_connection->connected)
			self::$_connection->connect();
	}

	public static function bind($parent, $value) {
		if ($value instanceof Artera_Mongo_Collection || $value instanceof Artera_Mongo_Cursor || $value instanceof Artera_Mongo_DB)
			return $value;

		if ($value instanceof MongoCollection)
			return new Artera_Mongo_Collection($value);
		if ($value instanceof MongoCursor) {
			if ($parent instanceof Artera_Mongo_Cursor)
				$parent = $parent->collection;
			return new Artera_Mongo_Cursor($parent, $value);
		}
		if ($value instanceof MongoDB)
			return new Artera_Mongo_DB($value);
		return $value;
	}

	public static function map($collection, $class) {
		self::$_map[$collection] = $class;
	}

	public static function documentCollection($class) {
		self::checkConnection();
		$name = array_search($class, self::$_map);
		return self::defaultDB()->selectCollection($name);
	}

	public static function documentOrSet($data, $path) {
		if (is_array($data)) {
			if (key($data) != null && !is_int(key($data)))
				$data = self::createDocument($data, $path);
			else
				$data = new Artera_Mongo_Document_Set($data, $path);
		}
		return $data;
	}

	public static function createDocument($data, $collection) {
		if (array_key_exists($collection, self::$_map))
			return new self::$_map[$collection]($data, false, $collection);
		else
			return new Artera_Mongo_Document($data, false, $collection);
	}

	public function selectDB($dbname) {
		return new Artera_Mongo_DB(parent::selectDB($dbname));
	}
}