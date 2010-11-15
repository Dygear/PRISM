<?php
class welcome extends Plugins
{
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

	public function __construct()
	{
		$this->registerPacket('onPrismConnect', ISP_VER);
#		$this->registerPacket('onClientConnect', ISP_NCN);
		$this->registerPacket('onChat', ISP_MSO);
	}

	public function onPrismConnect(IS_VER $VER)
	{
		$MSX = new IS_MSX;
		$MSX->Msg('PRISM Version ^3'.PHPInSimMod::VERSION.'^8 Has Connected.')->Send();
	}

	public function onClientConnect(IS_NCN $NCN)
	{
		$BTN = new IS_BTN;
		$BTN->ClickID(100)->UCID($NCN->UCID)->T(166)->L(29)->W(25)->H(6);
		$BTN->Text('Welcome to this ^3PRISM ^7Powered^8 Server.')->Send();
		$BTN->ClickID(101)->T($BTN->T + $BTN->H);
		$BTN->Text('PRISM Version ^7'.PHPInSimMod::VERSION.'^8.')->Send();
	}
	
	public function onChat(IS_MSO $MSO)
	{
		console($MSO->Msg);
	}
}
?>