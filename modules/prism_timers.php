<?php
class Timers
{
	protected $timers = array();	# Array of timers.
	protected $timeout = NULL;		# When the next timeout is, read only from outside of this class.

	// Registers a callback method.
	protected function createTimer($callback, $interval = 1.0, $flags = Timer::CLOSE, $args = array()) 
    { 
        # This will be the time when this timer is to trigger 
        $timestamp = microtime(TRUE) + $interval; 
         
        # Check to make sure that another timer with same timestamp doesn't exist 
        if (isset($this->timers["$timestamp"])) 
        { 
            $this->createTimer($callback, $interval, $flags, $args); 
        } 
        else  
        { 
            # Adds our timer to the array. 
            $this->timers["$timestamp"] = new Timer($this, $callback, $interval, $flags, $args); 
        } 
    }  

	// Sort the array to make sure the next timer (smallest float) is on the top of the list.
	protected function sortTimers()
	{
		return ksort($this->timers);
	}

	// Executes the elapsed timers, and returns when the next timer should execute or NULL if no timers are left.
	public function executeTimers()
	{
		if (empty($this->timers))
			return $this->timeout = NULL; # As we don't have any timers to check, we skip the rest of this function.

		$this->sortTimers();

		$timeNow = microtime(TRUE);

		foreach ($this->timers as $timestamp => &$timer)
		{
			# Check to see if the first timestamp has elpased.
			if ($timeNow < $timestamp)
				return; # If we are not past this timestamp, we go no further.

			# Here we execute expired timers.
			if ($timer->execute() != PLUGIN_STOP AND $timer->getFlags() != Timer::CLOSE)
				$this->createTimer($timer->getCallback(), $timer->getInterval(), $timer->getFlags(), $timer->getArgs());

			unset($this->timers[$timestamp]);
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
	protected $callback;
	protected $flags;
	protected $interval;

	public function __construct(&$parent, $callback, $interval = 1.0, $flags = Timer::CLOSE, $args = array())
	{
		$this->parent =& $parent;
		$this->setCallback($callback);
		$this->setInterval($interval);
		$this->setFlags($flags);
		$this->setArgs($args);
	}

	public function setArgs(array $args)	{ $this->args = $args; }
	public function getArgs()				{ return $this->args; }

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