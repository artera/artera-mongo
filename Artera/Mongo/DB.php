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
class Artera_Mongo_DB {
	public function __construct(MongoDB $db) {
		$this->db = $db;
	}

	public function __get($name) {
		Artera_Mongo::checkConnection();
		if (property_exists($this->db, $name))
			$ret = $this->db->$name;
		else
			$ret = $this->db->selectCollection($name);
		return Artera_Mongo::bind($this, $ret);
	}

	public function __call($name, $arguments) {
		if (in_array($name, array('listCollections')))
			Artera_Mongo::checkConnection();
		if (method_exists($this->db, $name))
			return Artera_Mongo::bind($this, call_user_func_array(array($this->db, $name), $arguments));
	}

	public function __toString() {
		return (string)$this->db;
	}
}