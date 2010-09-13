<?php
class theTime extends Plugins
{
	const NAME = 'The Time';
	const DESCRIPTION = 'Prints the time to the client who asks for it.';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;

	public function __construct(&$parent)
	{
		$this->parent =& $parent;
		$this->registerSayCommand('thetime', 'cmdTime', '- Displays the time.');
		$this->registerSayCommand('time', 'cmdTime', '- Displays the time.');
	}

	public function cmdTime($cmd, $plid, $ucid)
	{
		$MTC = new IS_MTC();
		$MTC->PLID = $plid;
		$MTC->Msg = 'The time is: ' . date('H:i:s') . ' server local time';
		$this->sendPacket($MTC);

		return PLUGIN_CONTINUE;
	}
}
?>