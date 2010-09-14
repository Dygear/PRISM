<?php
class admin extends Plugins
{
	const NAME = 'Admin Base';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Admin functions for PRISM.';

	public function __construct()
	{
		# Help
		$this->registerSayCommand('help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism help', 'cmdHelp', 'Displays this command list.');

		# Plugins
		$this->registerSayCommand('prism plugins', 'cmdPluginList', 'Displays a list of plugins.');
		$this->registerSayCommand('prism plugins list', 'cmdPluginList', 'Displays a list of plugins.');

		# Admins
		$this->registerSayCommand('prism admins', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins list', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins reload', 'cmdAdminReload', 'Reloads the admins.', ADMIN_CFG);
	}

	// Help
	public function cmdHelp($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)#  LEFT       LEFT
		echo sprintf("%32s %64s", 'COMMAND', 'DESCRIPTOIN') . PHP_EOL;
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
				echo sprintf("%32s - %64s", $command, $detail['info']) . PHP_EOL;
		}

		return PLUGIN_CONTINUE;
	}

	// Plugins
	public function cmdPluginList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		echo sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION') . PHP_EOL;
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			echo sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION) . PHP_EOL;
		}

		return PLUGIN_CONTINUE;
	}

	// Admins
	public function cmdAdminList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		echo sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION') . PHP_EOL;
		foreach ($PRISM->users->getUsers() as $user => $details)
		{
			echo $user;
			print_r($details);
		}

		return PLUGIN_CONTINUE;
	}

	public function cmdAdminReload($cmd, $plid, $ucid)
	{
		global $PRISM;

		print_r($PRISM->users->getUsers());

		return PLUGIN_CONTINUE;
	}
}
?>