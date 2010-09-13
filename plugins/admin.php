<?php

class admin extends Plugins
{
	const NAME = 'Admin Base';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Admin functions for PRISM.';

	public function __construct(&$parent)
	{
		$this->parent =& $parent;
		$this->registerSayCommand('help', 'cmdHelp', '- Displays this command list.');
		$this->registerSayCommand('prism help', 'cmdHelp', '- Displays this command list.');
		$this->registerSayCommand('prism admins list', 'cmdList', '- Displays a list of admins.');
		$this->registerSayCommand('prism admins reload', 'cmdLoad', '- Reloads the admins.', ADMIN_CFG);
	}

	public function cmdHelp($cmd, $plid, $ucid)
	{
		return PLUGIN_CONTINUE;
	}

	public function cmdList($cmd, $plid, $ucid)
	{
		return PLUGIN_CONTINUE;
	}

	public function cmdLoad($cmd, $plid, $ucid)
	{
		return PLUGIN_CONTINUE;
	}
}

?>