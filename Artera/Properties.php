<?php
/**
 * @category   Artera
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Properties {
	protected $_properties = array();

	public function __set($name, $value) {
		if (isset($this->_properties[$name])) {
			if (isset($this->_properties[$name]['setter'])) {
				if (method_exists($this, $this->_properties[$name]['setter']))
					$this->{$this->_properties[$name]['setter']}($value);
				else
					throw new Artera_Properties_Exception("Undefined setter for property '$name'.");
			} else throw new Artera_Properties_Exception_ReadOnly($name);
		} else throw new Artera_Properties_Exception_Undefined($name);
	}

	public function __get($name) {
		if (isset($this->_properties[$name])) {
			if (isset($this->_properties[$name]['getter'])) {
				$getter = $this->_properties[$name]['getter'];
				if (!empty($getter) && $getter[0]=='$') {
					$getter = substr($getter, 1);
					return $this->$getter;
				} elseif (method_exists($this, $getter)) {
					return $this->$getter();
				} else throw new Artera_Properties_Exception("Undefined getter for property '$name'.");
			} else throw new Artera_Properties_Exception("Undefined getter for property '$name'.");
		} else throw new Artera_Properties_Exception_Undefined($name);
	}
}