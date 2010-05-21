<?php
/**
 * @category   Artera
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Properties_Exception_Undefined extends Artera_Properties_Exception {
	public function __construct($property) {
		parent::__construct("Undefined property '$property'.");
	}
}