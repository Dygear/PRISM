<?php
class theTime extends plugins
{
	const NAME = 'The Time';
	const DESCRIPTION = 'Prints the time to the client who asks for it.';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;

	public function __construct(&$parent)
	{
		$this->parent =& $parent;
		$this->registerPacket('onSay', ISP_MSO, ISP_III);
	}

	public function onSay($p)
	{
		$M = substr($p->Msg, $p->TextStart);
		if ($M == '!thetime' OR $M == 'thetime' OR $M == '!time' OR $M == 'time')
		{
			$MTC = new IS_MTC();
			$MTC->PLID = ($p->PLID) ? $p->PLID : NULL;
			$MTC->UCID = ($p->UCID) ? $p->UCID : NULL;
			$MTC->Msg = 'The time is: ' . date('H:i:s') . ' server local time';
			$this->sendPacket($MTC);
		}
		return PLUGIN_CONTINUE;
	}
}
?>