<?php
class example extends Plugins
{
	const NAME = 'Example Plugin';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Used as a shell for quickly writing down ideas and seeing how they work.';

	public function __construct()
	{
		$this->registerPacket('onLFSChat', ISP_MSO);
	}

	public function onLFSChat($Packet)
	{
		var_dump($Packet);
	}
}
?>