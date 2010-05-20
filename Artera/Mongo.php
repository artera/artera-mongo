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
		if (preg_match('|/([a-zA-Z][a-zA-Z0-9_]*)$|', $server, $matches))
			$this->setDefaultDB($matches[1]);
	}

	/**
	 * Returns the default database either specified with setDefaultDB or auto-detected from the connection string
	 *
	 * @return Artera_Mongo_DB
	 */
	public static function defaultDB() {
		if (is_null(self::$_connection))
			throw new Artera_Mongo_Exception;
		else
			return self::$_connection->selectDB(self::$_defaultDB);
	}

	/**
	 * Sets the default database to use
	 *
	 * @param string $db
	 */
	public static function setDefaultDB($db) {
		self::$_defaultDB = $db;
	}

	/**
	 * Returns the last Mongo connection initialized
	 *
	 * @return Artera_Mongo
	 */
	public static function connection() {
		return self::$_connection;
	}

	/**
	 * Ensures that the connection to the server is extabilished
	 */
	public static function checkConnection() {
		if (!self::$_connection->connected)
			self::$_connection->connect();
	}

	/**
	 * Translates standard PECL Mongo classes to their Artera_Mongo equivalents when necessary
	 *
	 * @param mixed $parent
	 * @param mixed $value
	 * @return mixed
	 */
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

	/**
	 * Maps a mongodb collection to a document class
	 *
	 * @param string $collection
	 * @param string $class
	 */
	public static function map($collection, $class) {
		self::$_map[$collection] = $class;
	}

	/**
	 * Returns the mapped collection for the specified document class
	 *
	 * @param string $class
	 * @return Artera_Mongo_Collection
	 */
	public static function documentCollection($class) {
		self::checkConnection();
		$name = array_search($class, self::$_map);
		return self::defaultDB()->selectCollection($name);
	}

	/**
	 * Creates an instance of Artera_Mongo_Document or Artera_Mongo_Document_Set as necessary depending on the type of data supplied
	 *
	 * @param mixed $data
	 * @param string $path
	 * @return mixed
	 */
	public static function documentOrSet($data, $path) {
		if (is_array($data)) {
			if (key($data) != null && !is_int(key($data)))
				$data = self::createDocument($data, $path);
			else
				$data = new Artera_Mongo_Document_Set($data, $path);
		}
		return $data;
	}

	/**
	 * Creates the correct Artera_Mongo_Document instance for the specified collection
	 *
	 * @param mixed $data
	 * @param string $collection
	 * @return Artera_Mongo_Document
	 */
	public static function createDocument($data, $collection) {
		if (array_key_exists($collection, self::$_map))
			return new self::$_map[$collection]($data, false, $collection);
		else
			return new Artera_Mongo_Document($data, false, $collection);
	}

	/**
	 * Returns the specified database instance
	 *
	 * @param string $dbname
	 * @return Artera_Mongo_DB
	 */
	public function selectDB($dbname) {
		return new Artera_Mongo_DB(parent::selectDB($dbname));
	}
}