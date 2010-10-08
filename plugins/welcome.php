<?php
class welcome extends Plugins
{
	const NAME = 'Welcome & MOTD';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Welcome messages for clients, and Message of the Day (MOTD)';

	public function __construct()
	{
		# Announce
		$this->registerPacket(ISP_VER, 'onPrismConnect');
	}

	public function onPrismConnect()
	{
		$MSX = new IS_MSX;
		$MSX->Msg('PRISM Version '.PHPInSimMod::VERSION.' Has Connected.')->Send();
	}
}
?>