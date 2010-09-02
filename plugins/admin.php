<?php
class admin extends plugins
{
	const NAME = 'Admin Base';
	const DESCRIPTION = 'Allows for use of admin commands within PRISM.';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;

	public function __construct()
	{
		$this->registerPacket('onNewConnection', ISP_NCN);
		$this->registerPacket('onConnectionLeave', ISP_CNL);
		$this->registerPacket('onNewPlayer', ISP_NPL);
		$this->registerPacket('onPlayerLeave', ISP_PLP, ISP_PLL);
	}
	public function onNewConnection($packet)
	{
		print_r($packet);
		console('Called: admin->onNewConnection()');
		return PLUGIN_CONTINUE;
	}
	public function onConnectionLeave()
	{
		console('Called: admin->onConnectionLeave()');
		return PLUGIN_CONTINUE;
	}
	public function onNewPlayer()
	{
		console('Called: admin->onNewPlayer()');
		return PLUGIN_CONTINUE;
	}
	public function onPlayerLeave()
	{
		console('Called: admin->onPlayerLeave()');
		return PLUGIN_CONTINUE;
	}
}

?>