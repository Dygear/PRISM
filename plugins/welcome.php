<?php
class welcome extends Plugins
{
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

	public function __construct()
	{
		$this->registerPacket(ISP_VER, 'onPrismConnect'); # PRISM Connect Announce.
		$this->registerPacket(ISP_NCN, 'onClientConnect'); # Player Connect M.O.T.D.
	}

	public function onPrismConnect()
	{
		$MSX = new IS_MSX;
		$MSX->Msg('PRISM Version '.PHPInSimMod::VERSION.' Has Connected.')->Send();
	}
	
	public function onClientConnect(IS_NCN $NCN)
	{
#		$BTN = new IS_BTN;
#		$BTN->UCID($NCN->UCID)->Text('Welcome to a PRISM Powered Server.')->Send();
	}
}
?>