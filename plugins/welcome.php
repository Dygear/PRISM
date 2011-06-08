<?php
class welcome extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

	public function __construct()
	{
		$this->registerPacket('onPrismConnect', ISP_VER);
		$this->registerPacket('onClientConnect', ISP_NCN);
	}

	public function onPrismConnect(IS_VER $VER)
	{
		$MSX = new IS_MSX;
		$MSX->Msg('PRISM Version ^3'.PHPInSimMod::VERSION.'^8 Has Connected.')->Send();
	}

	public function onClientConnect(IS_NCN $NCN)
	{
		$BTN = new IS_BTN;
		$BTN->ClickID(100)->UCID($NCN->UCID)->T(IS_Y_MAX - IS_Y_MIN)->L(IS_X_MIN)->W(IS_X_MAX)->H(8);
		$BTN->Text('Welcome to this ^3PRISM ^7Powered^8 Server.')->Send();
		$BTN->ClickID(101)->T($BTN->T + $BTN->H);
		$BTN->Text('PRISM Version ^7'.PHPInSimMod::VERSION.'^8.')->Send();
		$this->createTimer('tmrClearWelcomeButtons', 15, Timer::CLOSE, array($NCN->UCID));
	}
	public function tmrClearWelcomeButtons($UCID)
	{
		$BNF = new IS_BFN;
		$BNF->SubT(BFN_DEL_BTN)->UCID($UCID);
		$BNF->ClickID(100)->Send();
		$BNF->ClickID(101)->Send();
	}
}
?>