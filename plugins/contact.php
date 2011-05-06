<?php
class contact extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Contact';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Collision Detection Plugin.';

	public function __construct() {
		$this->registerPacket('onCON', ISP_CON);
	}
	public function onCON($Packet) {
		print_r($Packet);
	}
}
?>