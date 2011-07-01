<?php
class theTime extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Gnomon';
	const DESCRIPTION = 'Prints the time to the client who asks for it.';
	const AUTHOR = "Mark 'Dygear' Tomlin";
	const VERSION = PHPInSimMod::VERSION;

	public function __construct()
	{
		$this->registerSayCommand('thetime', 'cmdTime', 'Displays the time.');
		$this->registerSayCommand('time', 'cmdTime', 'Displays the time.');
	}

	public function cmdTime($cmd, $ucid)
	{
		IS_MTC()->UCID($ucid)->Text('^7The time is, '.date('g:i:s A (H:i:s), T.'))->Send();
		return PLUGIN_HANDLED;
	}
}
?>