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
			} else {
				throw new Artera_Properties_Exception_ReadOnly($name);
			}
		} else {
			throw new Artera_Properties_Exception_Undefined($name);
		}
	}

	public function __get($name) {
		if (isset($this->_properties[$name])) {
			if (isset($this->_properties[$name]['getter']) && method_exists($this, $this->_properties[$name]['getter'])) {
				return $this->{$this->_properties[$name]['getter']}();
			} elseif (isset($this->_properties[$name]['var'])) {
				return $this->{$this->_properties[$name]['var']};
			} else {
				throw new Artera_Properties_Exception("Undefined getter/var for property '$name'.");
			}
		} else {
			throw new Artera_Properties_Exception_Undefined($name);
		}
	}
}