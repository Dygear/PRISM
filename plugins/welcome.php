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
		IS_MSX()->Msg('PRISM Version ^3'.PHPInSimMod::VERSION.'^8 Has Connected.')->Send();
	}

	public function onClientConnect(IS_NCN $NCN)
	{
		if ($NCN->UCID == 0)
			return;
		
		$Title = new Button('poweredBy', Button::$TO_ALL);
		$Title->Text('This server is powered by');
		$Title->registerOnClick($this, 'onPoweredByClick');
		$Title->T(IS_Y_MAX - IS_Y_MIN)->L(IS_X_MIN)->W(IS_X_MAX)->H(8)->send();
		
		$Msg = new Button('prism', Button::$TO_ALL);
		$Msg->Text('^3PRISM ^8Version ^7'.PHPInSimMod::VERSION.'^8.');
		$Msg->T(IS_Y_MAX - IS_Y_MIN + 8)->L(IS_X_MIN)->W(IS_X_MAX)->H(8)->send();
	}
	
	public function onPoweredByClick(IS_BTC $BTC)
	{
		echo 'Button clicked! ' . $BTC;
	}
}
?>