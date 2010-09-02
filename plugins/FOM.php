<?php

class FOM extends plugins
{
	const NAME = 'Formula One Management';
	const DESCRIPTION = 'F1 Style Overlays for Live For Speed via Buttons';
	const AUTHOR = 'Dygear';
	const VERSION = PHPInSimMod::VERSION;

	public function __construct()
	{
		$this->registerPacket('onNewPlayer', ISP_NPL);
		$this->registerPacket('onPlayerLeave', ISP_PLP);
		$this->registerPacket('onSector', ISP_SPX, ISP_LAP);
	}

	public function onNewPlayer($packet)
	{
		console('FOM::onNewPlayer');
	}
	public function onPlayerLeave($packet)
	{
		console('FOM::onPlayerLeave');
	}
	public function onSector($packet)
	{
		console('FOM::onSector');
	}
}

?>