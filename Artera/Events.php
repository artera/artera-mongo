<?php
/**
 * The Artera_Events class implements the required functionality for events dispatching.
 * @category   Artera
 * @copyright  Artera S.r.l.
 * @license    New BSD License
 * @author     Massimiliano Torromeo
 */
class Artera_Events {
	protected $_events = array();

	/**
	 * Attach an event by name. The callback will be called when the named event is fired.
	 * Example usage:
	 * <code>
	 * <?php
	 * function test { echo 'Process completed!'; }
	 * $myclass->addEvent('onComplete', 'test');
	 * ?>
	 * </code>
	 * @param string $name The name of the event.
	 * @param mixed $callback The callback to be called when the event is fired.
	 */
	public function addEvent($name, $callback) {
		if (!isset($this->_events[$name]))
			$this->_events[$name] = array();
		$this->_events[$name][] = $callback;
	}

	/**
	 * Fires the named event. The specified arguments will be passed to the registered callbacks.
	 * Example usage:
	 * <code>
	 * <?php
	 * $myclass->fireEvent('onComplete');
	 * ?>
	 * </code>
	 * @see addEvent()
	 * @param string $name The name of the event to fire.
	 * @param mixed $arguments The arguments to pass to the registered callbacks.
	 */
	public function fireEvent($name, $arguments=array()) {
		if (!isset($this->_events[$name])) return;
		foreach ($this->_events[$name] as $event) {
			call_user_func_array($event, $arguments);
		}
	}
}