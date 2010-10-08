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
		$this->registerSayCommand('prism version', 'cmdVersion', 'Displays the version of PRISM.');
/*
		# Plugins
		$this->registerSayCommand('prism plugins', 'cmdPluginList', 'Displays a list of plugins.');
		$this->registerSayCommand('prism plugins list', 'cmdPluginList', 'Displays a list of plugins.');

		# Admins
		$this->registerSayCommand('prism admins', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins list', 'cmdAdminList', 'Displays a list of admins.');
*/
		# Admin Commands
		$this->registerSayCommand('prism kick', 'castLFSCommand', '<targets> ...', ADMIN_KICK);
		$this->registerSayCommand('prism ban', 'cmdAdminBan', ' <target> <time>', ADMIN_BAN);
		$this->registerSayCommand('prism spec', 'castLFSCommand', '<targets> ...', ADMIN_SPECTATE);
		$this->registerSayCommand('prism pit', 'castLFSCommand', '<targets> ...', ADMIN_SPECTATE);
	}

	public function cmdAdminBan($cmd, $plid, $ucid)
	{
		# Get the command and it's args.
		$argv = str_getcsv($cmd, ' ');
		array_shift($argv); $cmd = array_shift($argv);

		$castingAdmin = $this->getUserNameByUCID($ucid);

		$target = array_shift($argv);
		$minutes = array_shift($argv);

		# If we don't have target(s), then we can't do anything.
		if (count($argv) == 0)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Msg("$cmd needs a target.")->Send();
		}
		else if ($castingAdmin == $target)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Msg('Why would you even try to run this on yourself?')->Send();
		}
		else if ($this->isImmune($target))
		{
			$MSX = new IS_MSX;
			$MSX->Msg("Admin $castingAdmin tired to kick immune Admin $target.")->Send();
		}
		else
		{
			$MST = new IS_MST();
			$MST->Msg("/ban $target $minutes")->Send();
			$MSX = new IS_MSX;
			$MSX->Msg("Admin $castingAdmin banned $target for $minutes minute(s).");
		}
	}

	public function castLFSCommand($cmd, $plid, $ucid)
	{
		# Get the command and it's args.
		$argv = str_getcsv($cmd, ' ');
		array_shift($argv); $cmd = array_shift($argv);

		$castingAdmin = $this->getUserNameByUCID($ucid);

		# If we don't have target(s), then we can't do anything.
		if (count($argv) == 0)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Msg("$cmd needs a target.")->Send();
		}
		else
		{
			foreach ($argv as $target)
			{
				if ($castingAdmin == $target)
				{
					$MTC = new IS_MTC;
					$MTC->UCID($ucid)->Msg('Why would you even try to run this on yourself?')->Send();
				}
				else if ($this->isImmune($target))
				{
					$MSX = new IS_MSX;
					$MSX->Msg("Admin $castingAdmin tired to kick immune Admin $target.")->Send();
				}
				else
				{
					$MST = new IS_MST();
					$MST->Msg("/{$cmd} $target")->Send();
					$MSX = new IS_MSX;
					$MSX->Msg("Admin $castingAdmin {$cmd}ed $target.")->Send();
				}
			}
		}
	}

	// Help
	public function cmdHelp($cmd, $plid, $ucid)
	{
		global $PRISM;

		// (For button alignments)#  LEFT       LEFT
		echo sprintf("%32s - %64s", 'COMMAND', 'DESCRIPTION') . PHP_EOL;
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

		$MTC = new IS_MTC;
		$MTC->PLID($plid);

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		$MTC->Msg(sprintf('%28s %8s %24s %64s', 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION'))->Send();
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
			$MTC->Msg(sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION))->Send();

		return PLUGIN_CONTINUE;
	}

	// Version
	public function cmdVersion($cmd, $plid, $ucid)
	{
		$MTC = new IS_MTC();
		$MTC->UCID($ucid)->Msg('PRISM Version ' . PHPInSimMod::VERSION)->Send();
	}

	// Admins
	public function cmdAdminList($cmd, $plid, $ucid)
	{
		global $PRISM;

		$MTC = new IS_MTC;
		$MTC->PLID($plid);

		// (For button alignments)		#  MIDDLE    MIDDLE   RIGHT     LEFT
		$MTC->Msg(sprintf("%28s %8s %24s %64s", 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION'))->Send();
		foreach ($PRISM->admins->getAdminsInfo() as $user => $details)
		{
			$MTC->Msg($user)->Send();
			print_r($details);
		}

		return PLUGIN_CONTINUE;
	}
}
?>