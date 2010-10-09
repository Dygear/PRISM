<?php
class theTime extends Plugins
{
	const NAME = 'Time Teller';
	const DESCRIPTION = 'Prints the time to the client who asks for it.';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;

	public function __construct()
	{
		$this->registerSayCommand('thetime', 'cmdTime', 'Displays the time.');
		$this->registerSayCommand('time', 'cmdTime', 'Displays the time.');
	}

	public function cmdTime($cmd, $plid, $ucid)
	{
		$MTC = new IS_MTC();
		$MTC->UCID($ucid)->Msg('The time is, '.date('H:i:s').', server local time.')->send();
		return PLUGIN_CONTINUE;
	}
}
?>