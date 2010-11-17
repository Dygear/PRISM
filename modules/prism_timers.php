<?php

class Timer extends Plugin
{
	const TIMER_CLOSE = 0;	/** Fires once, then closes. */
	const TIMER_REPEAT = 1; /** Fires repeatedly, until callback returns PLUGIN_STOP, or timer is invalidated. */

	private $timers = array();

	public function handleTick()
	{
		if (empty($this->timers))
			return PLUGIN_CONTINUE;
		
		foreach ($this->timers as $ID => $Data)
		{
			if ($)
		}
	}

	public function createTimer($interval, $callback, $args = NULL, $flags = TIMER_CLOSE)
	{
		$this->timers[] = array(
			'interval' => $interval,
			'callback' => $callback,
			'args' => $args,
			'flags' => $flags
		); 
	}
}

?>