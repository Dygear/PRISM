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

		# Admin Commands
		$this->registerSayCommand('prism kick', 'cmdAdminKick', '<client>');
		$this->registerSayCommand('prism ban', 'cmdAdminBan', ' <client> <time>');
		$this->registerSayCommand('prism spec', 'cmdAdminSpec', '<player/client>');
		$this->registerSayCommand('prism pit', 'cmdAdminPit', '<player/client>');
	}

	public function cmdAdminKick($cmd, $plid, $ucid)
	{
		console("$cmd, $plid, $ucid");
		$this->getUserByPLID($plid);
		$this->getUserByUCID($ucid);
	}
	public function cmdAdminBan($cmd, $plid, $ucid)
	{
		console("$cmd, $plid, $ucid");
	}
	public function cmdAdminSpec($cmd, $plid, $ucid)
	{
		console("$cmd, $plid, $ucid");
	}
	public function cmdAdminPit($cmd, $plid, $ucid)
	{
		console("$cmd, $plid, $ucid");
	}

	// Help
	public function cmdHelp($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)#  LEFT       LEFT
		echo sprintf("%32s - %64s", 'COMMAND', 'DESCRIPTOIN') . PHP_EOL;
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
			{
				console(sprintf('%32s - %64s', $command, $detail['info']));
			}
		}

		return PLUGIN_CONTINUE;
	}

	// Plugins
	public function cmdPluginList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		console(sprintf('%28s %8s %24s %64s', 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION'));
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			console(sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION));
		}

		return PLUGIN_CONTINUE;
	}

	// Admins
	public function cmdAdminList($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		echo sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION') . PHP_EOL;
		foreach ($PRISM->admins->getAdminsInfo() as $user => $details)
		{
			echo $user;
			print_r($details);
		}

		return PLUGIN_CONTINUE;
	}
}
?>