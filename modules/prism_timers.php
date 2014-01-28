<?php
class Timers
{
	protected $timers = array();	# Array of timers.
	protected $timeout = NULL;		# When the next timeout is, read only from outside of this class.

	// Registers a callback method.
	protected function createTimer($callback, $interval = 1.0, $flags = Timer::CLOSE, $args = array())
	{
		# Uniqe Timer ID based on time in microseconds prepended by a random number
		$name = uniqid(mt_rand(), true);
		$this->createNamedTimer($name, $callback, $interval, $flags, $args);
	}

	// Create a timer with a name so that it can be removed on demand
	protected function createNamedTimer($name, $callback, $interval = 1.0, $flags = Timer::CLOSE, $args = array())
	{
		# Adds our timer to the array.
		$this->timers["$name"] = new Timer($this, $callback, $interval, $flags, $args);
	}

	// UnRegisters a callback method.
	protected function removeTimer($name)
	{
		# removes our timer from the array.
		unset($this->timers["$name"]);
	}

	// Executes the elapsed timers, and returns when the next timer should execute or NULL if no timers are left.
	public function executeTimers()
	{
		if (empty($this->timers))
			return $this->timeout = NULL; # As we don't have any timers to check, we skip the rest of this function.

		$timeNow = microtime(TRUE);
		$timestamp = null;

		foreach ($this->timers as $name => &$timer)
		{
			 $timerTS = $timer->getTimeStamp();
			# Check to see if the first timestamp has elpased.
			if ($timeNow < $timerTS)
				continue; # If we are not past this timestamp, we go no further for this timer.
			
			# decide next timeout timestamp
			if ($timestamp = null || $timerTS > $timestamp)
				$timestamp = $timerTS;

			# Here we execute expired timers.
			if ($timer->execute() != PLUGIN_STOP AND $timer->getFlags() != Timer::CLOSE) {
				# Update Timer TimeStamp
				$timer->setTimeStamp($timerTS + (float)$timer->getInterval());
			}
			else
			{
				unset($this->timers["$name"]);
			}
		}

		$this->timeout = $timestamp;

		if (empty($this->timers))
			return NULL;
		else
			return $this->timeout;
	}
}

class Timer
{
	const CLOSE = 0; /** Timer will run once, the default behavior. */
	const REPEAT = 1; /** Timer will repeat until it returns PLUGIN_STOP. */
	const FOREVER = -1; /** Timer will repeat forever, or until the callback function returns PLUGIN_STOP */

	protected $parent;
	protected $args;
	protected $timestamp;
	protected $callback;
	protected $flags;
	protected $interval;

	public function __construct(&$parent, $callback, $interval = 1.0, $flags = Timer::CLOSE, $args = array())
	{
		$this->parent =& $parent;
		$this->setCallback($callback);
		$this->setTimeStamp(microtime(TRUE) + (float)$interval);
		$this->setInterval($interval);
		$this->setFlags($flags);
		$this->setArgs($args);
	}

	public function setArgs(array $args)	{ $this->args = $args; }
	public function getArgs()				{ return $this->args; }

	public function setTimeStamp($timestamp){ $this->timestamp = $timestamp; }
	public function getTimeStamp()			{ return $this->timestamp; }

	public function setCallback($callback)	{ $this->callback = $callback; }
	public function getCallback()			{ return $this->callback; }

	public function setFlags($flags)		{ $this->flags = $flags; }
	public function getFlags()				{ return $this->flags; }

	public function setInterval($interval)	{ $this->interval = $interval; }
	public function getInterval()			{ return $this->interval; }

/*	public function setRepeat($repeat)		{ $this->repeat = (int) $repeat; }
	public function getRepeat()				{ return $this->repeat; } */

	public function execute()
	{
		return call_user_func_array(array(&$this->parent, $this->callback), $this->args);
	}
}
?>
