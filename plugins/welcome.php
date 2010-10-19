<?php
class welcome extends Plugins
{
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

	public function __construct()
	{
		$this->registerPacket(ISP_VER, 'onPrismConnect');
		$this->registerPacket(ISP_NCN, 'onClientConnect');
		$this->registerSayCommand('ncn', 'onClientConnect');
	}

	public function onPrismConnect()
	{
		$MSX = new IS_MSX;
		$MSX->Msg('PRISM Version ^3'.PHPInSimMod::VERSION.'^8 Has Connected.')->Send();
	}

	public function onClientConnect(IS_NCN $NCN)
	{
		$BTN = new IS_BTN;
		$BTN->UCID($NCN->UCID)->W(50)->H(5);
		$BTN->T(IS_Y_MAX - 5)->Text('Welcome to this ^3PRISM ^7Powered^8 Server.')->Send();
		$BTN->ClickID(++$BTN->ClickID)->T(IS_Y_MAX)->Text('PRISM Version ^7'.PHPInSimMod::VERSION.'^8.')->Send();
	}
}
?>