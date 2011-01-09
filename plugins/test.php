<?php
class test extends Plugins
{
	const NAME = 'Test';
	const DESCRIPTION = 'Test bed plugin, I hope I delete this before I commit to gitub.';
	const AUTHOR = "Mark 'Dygear' Tomlin";
	const VERSION = PHPInSimMod::VERSION;

	public function __construct()
	{
		$this->createTimer('tmrCallback', 0.1, Timer::REPEAT);
	}

	public $count = 0;
	public function tmrCallback()
	{
		echo __METHOD__ . ++$this->count . PHP_EOL;

		if ($this->count < 50)
			return PLUGIN_CONTINUE;
		else
			return PLUGIN_STOP;
	}
}
?>