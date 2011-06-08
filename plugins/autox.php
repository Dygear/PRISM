<?php
class admin extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'AutoX';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Auto Cross functions for PRISM.';

	private $globalObjects = array(); # Global Objects Owned By All.
	private $clientObjects = array(); # Objects Owned By One Client.

	public function __construct()
	{
		$this->registerSayCommand('autox object list', 'cmdObjects', 'Returns a list of objects that have been saved', ADMIN_OBJECT);
		$this->registerSayCommand('autox make start', 'cmdMakeStart', '<object> - Allows you to make an object', ADMIN_OBJECT);
		$this->registerSayCommand('autox make end', 'cmdMakeEnd', 'Saves details of your currently opened object', ADMIN_OBJECT);
		$this->registerSayCommand('autox build', 'cmdBuild', '<object> - Builds an object around an placed AutoX object.', ADMIN_OBJECT);
	}

	public function cmdObjects($cmd, $ucid)
	{
		print_r($this->globalObjects);
		print_r($this->clientObjects);
	}
	public function cmdMakeStart($cmd, $ucid){}
	public function cmdMakeEnd($cmd, $ucid){}
	public function cmdBuild($cmd, $ucid){}
}
?>