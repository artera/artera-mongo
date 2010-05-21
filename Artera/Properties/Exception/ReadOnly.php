<?php
/**
 * @category   Artera
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Properties_Exception_ReadOnly extends Artera_Properties_Exception {
	public function __construct($property) {
		parent::__construct("Property '$property' is read-only.");
	}
}