<?php
class admin extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Admin Base';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Admin functions for PRISM.';

	public function __construct()
	{
		# Exit
		$this->registerSayCommand('prism exit', 'cmdEnd', 'Shuts down PRISM.', ADMIN_CVAR);
	
		# Help
		$this->registerSayCommand('help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism version', 'cmdVersion', 'Displays the version of PRISM.');

		# Admins
		$this->registerSayCommand('prism admins', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins list', 'cmdAdminList', 'Displays a list of admins.');

		# Plugins
		$this->registerSayCommand('prism plugins', 'cmdPluginList', 'Displays a list of plugins.');
		$this->registerSayCommand('prism plugins list', 'cmdPluginList', 'Displays a list of plugins.');

		# Admin Commands
		$this->registerSayCommand('prism ban', 'cmdAdminBan', ' <target> <time> - Ban Client.', ADMIN_BAN);
		$this->registerSayCommand('prism kick', 'castLFSCommand', '<targets> ... - Kick Client.', ADMIN_KICK);
		$this->registerSayCommand('prism pit', 'castLFSCommand', '<targets> ... - Pit Client.', ADMIN_SPECTATE);
		$this->registerSayCommand('prism spec', 'castLFSCommand', '<targets> ... - Spectate Client.', ADMIN_SPECTATE);

		$this->registerSayCommand('prism rcon', 'cmdRCON', '"<command>" - Remote Console Commands', ADMIN_CFG + ADMIN_UNIMMUNIZE);
		
		$this->registerSayCommand('prism rcm', 'cmdRaceControlMessagePlayer', '"Msg" <USERNAME> <Time> - Race Control Messages', ADMIN_RCM);
		$this->registerSayCommand('prism rcm all', 'cmdRaceControlMessageAll', '"Msg" <Time> - Race Control Messages', ADMIN_RCM);
	}

	public function cmdExit($cmd, $ucid)
	{
		die("PRISM killed by client: {$this->getClientUNameByUCID($ucid)}.");
	}

	// Race Control Messages
	public function cmdRaceControlMessagePlayer($cmd, $ucid)
	{
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 4)
			$this->createTimer('tmrClearRCM', $argv[4], Timer::Close, $argv[3]);
		else
			$this->createTimer('tmrClearRCM', 5);

		$argv = $this->raceControlMessage($cmd);

		$MST = new IS_MST();
		$MST->Msg("/rcm {$argv[2]}")->Send();
		$MST->Msg("/rcm_ply {$argv[3]}")->Send();
	}

	public function cmdRaceControlMessageAll($cmd, $ucid)
	{
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 3)
			$this->createTimer('tmrClearRCM', $argv[3]);
		else
			$this->createTimer('tmrClearRCM', 5);

		$argv = $this->raceControlMessage($cmd);

		$MST = new IS_MST();
		$MST->Msg("/rcm {$argv[2]}")->Send();
		$MST->Msg("/rcm_all")->Send();
	}

	public function tmrClearRCM($args = NULL)
	{
		print_r($args);
		$MST = new IS_MST();
		$MST->Msg("/rcc_all")->Send();
		$MST->Msg("/rcc_ply {$argv[3]}")->Send();
	}

	public function cmdRCON($cmd, $ucid)
	{
		$argv = str_getcsv($cmd, ' ');
		$MST = new IS_MST();
		$MST->Msg(array_pop($argv))->Send();
	}

	public function cmdAdminBan($cmd, $ucid)
	{
		# Get the command and it's args.
		$argv = str_getcsv($cmd, ' ');
		array_shift($argv); $cmd = array_shift($argv);
		$target = array_shift($argv); $minutes = array_shift($argv);
		
		$castingAdmin = $this->getClientByUCID($ucid);
		
		# If we don't have target(s), then we can't do anything.
		if (count($argv) == 0)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Text("$cmd needs a target.")->Send();
		}
		else if ($castingAdmin->UName == $target)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Text('Why would you even try to run this on yourself?')->Send();
		}
		else if ($this->isImmune($target))
		{
			$MSX = new IS_MSX;
			$MSX->Msg("Admin {$castingAdmin->UName} tired to kick immune Admin $target.")->Send();
		}
		else
		{
			$MST = new IS_MST();
			$MST->Msg("/ban $target $minutes")->Send();
			$MSX = new IS_MSX;
			$MSX->Msg("Admin {$castingAdmin->UName} banned $target for $minutes minute(s).");
		}
	}

	public function castLFSCommand($cmd, $ucid)
	{
		# Get the command and it's args.
		$argv = str_getcsv($cmd, ' ');
		array_shift($argv); $cmd = array_shift($argv);

		$castingAdmin = $this->getClientByUCID($ucid);

		# If we don't have target(s), then we can't do anything.
		if (count($argv) == 0)
		{
			$MTC = new IS_MTC;
			$MTC->UCID($ucid)->Text("$cmd needs a target.")->Send();
		}
		else
		{
			foreach ($argv as $target)
			{
				if ($castingAdmin->UName == $target)
				{
					$MTC = new IS_MTC;
					$MTC->UCID($ucid)->Text('Why would you even try to run this on yourself?')->Send();
				}
				else if ($this->isImmune($target))
				{
					$MSX = new IS_MSX;
					$MSX->Msg("Admin {$castingAdmin->UName} tired to kick immune Admin $target.")->Send();
				}
				else
				{
					$MST = new IS_MST();
					$MST->Msg("/{$cmd} $target")->Send();
					$MSX = new IS_MSX;
					$MSX->Msg("Admin {$castingAdmin->UName} {$cmd}ed $target.")->Send();
				}
			}
		}
	}

	// Help
	public function cmdHelp($cmd, $ucid)
	{
		global $PRISM;
		$MTC = new IS_MTC;
		$MTC->Sound(SND_SYSMESSAGE)->UCID($ucid);

		$MTC->Text('^7COMMAND^8 - DESCRIPTION')->Send();
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
				$MTC->Text("^7{$command}^8 - {$detail['info']}")->Send();
		}

		return PLUGIN_CONTINUE;
	}

	// Plugins
	public function cmdPluginList($cmd, $ucid)
	{
		global $PRISM;

		$MTC = new IS_MTC;
		$MTC->UCID($ucid);

		$MTC->Text('^7NAME ^3VERSION ^8AUTHOR')->Send();
		$MTC->Text('DESCRIPTION')->Send();

		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			$MTC->Text(sprintf('^7%s ^3%s ^8%s', $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR))->Send();
			$MTC->Text($plugin::DESCRIPTION)->Send();
		}

		return PLUGIN_CONTINUE;
	}

	// Version
	public function cmdVersion($cmd, $ucid)
	{
		$MTC = new IS_MTC();
		$MTC->UCID($ucid)->Text('PRISM Version ^7' . PHPInSimMod::VERSION)->Send();
	}

	// Admins
	public function cmdAdminList($cmd, $ucid)
	{
		global $PRISM;

		$MTC = new IS_MTC;
		$MTC->UCID($ucid)->Text('Admins detailed to this server:')->Send();

		foreach ($PRISM->admins->getAdminsInfo() as $user => $details)
			$MTC->Text("    $user")->Send();

		return PLUGIN_CONTINUE;
	}
}
?>