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
		$this->registerSayCommand('prism exit', 'cmdExit', 'Shuts down PRISM.', ADMIN_ADMIN);
	
		# Help
		$this->registerSayCommand('help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism help', 'cmdHelp', 'Displays this command list.');
		$this->registerSayCommand('prism version', 'cmdVersion', 'Displays the version of PRISM.');

		# Admins
		$this->registerSayCommand('prism admins list', 'cmdAdminList', 'Displays a list of admins.');
		$this->registerSayCommand('prism admins', 'cmdAdminList', 'Displays a list of admins.');

		# Plugins
		$this->registerSayCommand('prism plugins load', 'cmdPluginLoad', '<plugin> ... - Loads plugin(s) at runtime.', ADMIN_CFG | ADMIN_CVAR);
		$this->registerSayCommand('prism plugins unload', 'cmdPluginUnload', '<plugin> ... - Unloads plugin(s) at runtime.', ADMIN_CFG | ADMIN_CVAR);
		$this->registerSayCommand('prism plugins list', 'cmdPluginList', 'Displays a list of plugins.', ADMIN_CFG | ADMIN_CVAR);
		$this->registerSayCommand('prism plugins', 'cmdPluginList', 'Displays a list of plugins.', ADMIN_CFG | ADMIN_CVAR);

		# Admin Commands
		$this->registerSayCommand('prism ban', 'castLFSCommand', '<time> <targets> - Ban Client.', ADMIN_BAN);
		$this->registerSayCommand('prism kick', 'castLFSCommand', '<targets> ... - Kick Client.', ADMIN_KICK);
		$this->registerSayCommand('prism pit', 'castLFSCommand', '<targets> ... - Pit Client.', ADMIN_SPECTATE);
		$this->registerSayCommand('prism spec', 'castLFSCommand', '<targets> ... - Spectate Client.', ADMIN_SPECTATE);

		$this->registerSayCommand('prism rcon', 'cmdRCON', '"<command>" - Remote Console Commands', ADMIN_CFG | ADMIN_UNIMMUNIZE);
		
		$this->registerSayCommand('prism rcm', 'cmdRaceControlMessagePlayer', '"Msg" <USERNAME> <Time> - Race Control Messages', ADMIN_RCM);
		$this->registerSayCommand('prism rcm all', 'cmdRaceControlMessageAll', '"Msg" <Time> - Race Control Messages', ADMIN_RCM);
	}

	public function cmdExit($cmd, $ucid)
	{
		die("PRISM killed by client: {$this->getClientByUCID($ucid)->UName}." . PHP_EOL);
	}

	// Race Control Messages
	public function cmdRaceControlMessagePlayer($cmd, $ucid)
	{
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 4)
			$this->createTimer('tmrClearRCM', $argv[4], Timer::Close, $argv[3]);
		else
			$this->createTimer('tmrClearRCM', 5);

		$argv = $this->raceControlMessage($cmd);

		IS_MST()->Msg("/rcm {$argv[2]}")->Send();
		IS_MST()->Msg("/rcm_ply {$argv[3]}")->Send();

		return PLUGIN_HANDLED;
	}

	public function cmdRaceControlMessageAll($cmd, $ucid)
	{
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 3)
			$this->createTimer('tmrClearRCM', $argv[3]);
		else
			$this->createTimer('tmrClearRCM', 5);

		$argv = $this->raceControlMessage($cmd);

		IS_MST()->Msg("/rcm {$argv[2]}")->Send();
		IS_MST()->Msg("/rcm_all")->Send();

		return PLUGIN_HANDLED;
	}

	public function tmrClearRCM($args = NULL)
	{
		IS_MST()->Msg("/rcc_all")->Send();
		IS_MST()->Msg("/rcc_ply {$argv[3]}")->Send();
	}

	public function cmdRCON($cmd, $ucid)
	{
		$argv = str_getcsv($cmd, ' ');
		IS_MST()->Msg(array_pop($argv))->Send();

		return PLUGIN_HANDLED;
	}

	public function castLFSCommand($cmd, $ucid)
	{
		# Get the command and it's args.
		$argc = count($argv = str_getcsv($cmd, ' '));

		array_shift($argv);
		$cmd = array_shift($argv);
		if ($cmd == 'ban')
			$minutes = array_shift($argv);
		
		if (($cmd == 'ban' && $argc < 4) || $argc < 3)
		{
			IS_MTC()->UCID($ucid)->Text("Useage: `prism {$cmd}" . (($cmd == 'ban') ? ' <time>' : '') . ' <targets> ...`')->Send();
			return PLUGIN_HANDLED;
		}
		
		$castingAdmin = $this->getClientByUCID($ucid);
		
		# If we don't have target(s), then we can't do anything.
		foreach ($argv as $target)
		{
			$target = strToLower($target);
			if (strToLower($castingAdmin->UName) == $target)
			{
				IS_MTC()->UCID($ucid)->Text('Why would you even try to run this on yourself?')->Send();
			}
			else if ($this->isImmune($target))
			{
				IS_MSX()->Msg("Admin {$castingAdmin->UName} tired to {$cmd} immune Admin $target.")->Send();
			}
			else
			{
				IS_MST()->Msg("/{$cmd} $target")->Send();
				IS_MSX()->Msg("Admin {$castingAdmin->UName} {$cmd}'ed $target.")->Send();
			}
		}
		
		return PLUGIN_HANDLED;
	}

	// Help
	public function cmdHelp($cmd, $ucid)
	{
		global $PRISM;
		$MTC = IS_MTC()->Sound(SND_SYSMESSAGE)->UCID($ucid);

		$requestingClient = $this->getClientByUCID($ucid);

		$MTC->Text('^7COMMAND^8 - DESCRIPTION')->Send();
		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			foreach ($details->sayCommands as $command => $detail)
			{
				#something is wrong here.
				if ($requestingClient->getAccessFlags() & $detail['accessLevel'])
					$MTC->Text("^7{$command}^8 - {$detail['info']}")->Send();
			}
		}

		return PLUGIN_HANDLED;
	}

	// Plugins
	public function cmdPluginList($cmd, $ucid)
	{
		global $PRISM;

		$MTC = IS_MTC()->UCID($ucid);

		$MTC->Text('^7NAME ^3VERSION ^8AUTHOR')->Send();
		$MTC->Text('DESCRIPTION')->Send();

		foreach ($PRISM->plugins->getPlugins() as $plugin => $details)
		{
			# RED = Unloadable (Not Sane), YELLOW = Loadable (Not Loaded, Sane), GREEN = Loaded. // For Later Use
			$MTC->Text(sprintf('^7%s ^3%s ^8%s', $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR))->Send();
			$MTC->Text($plugin::DESCRIPTION)->Send();
		}

		return PLUGIN_HANDLED;
	}
	
	public function cmdPluginLoad($cmd, $ucid)
	{
		global $PRISM;
		
		$MTC = IS_MTC()->UCID($ucid);
		
		if (($argc = count($argv = str_getcsv($cmd, ' '))) < 4)
		{
			$MTC->Text('Useage: `prism plugins load <plugin>`')->Send();
			$MTC->Text('Loads plugin(s) at runtime.')->Send();
			
			$PluginsAll = get_dir_structure(PHPInSimMod::ROOTPATH . '/plugins/');
			$PluginsLoaded = array_keys($PRISM->plugins->getPlugins());
			$PluginsNotloaded = array_diff($PluginsAll, $PluginsLoaded);

			var_dump($PluginsAvailable, $PluginsLoaded, $PluginsNotloaded);

/*			foreach ($PluginsNotloaded as $Plugin)
			{
				if (validatePHPFile(PHPInSimMod::ROOTPATH . '/plugins/' . $Plugins . '.php'))
					#Plugin Is Sane, Color Should be GREEN.
				else
					#Plugin Not Sname, Color Should be RED.
			} */
		}

		#Load Plugins
		
		return PLUGIN_HANDLED;
	}

	public function cmdPluginUnload($cmd, $ucid)
	{
		global $PRISM;

		$MTC = IS_MTC()->UCID($ucid);
		
		if (($argc = count($argv = str_getcsv($cmd, ' '))) < 4)
		{
			$MTC->Text('Useage: `prism plugins unload <plugin>`')->Send();
			$MTC->Text('Unloads plugin(s) at runtime.')->Send();
			
			$PluginsLoaded = array_keys($PRISM->plugins->getPlugins());

/*			$MTC->Text('You can unload any of the following plugins.')->Send();
			foreach ($PluginsLoaded as $Plugin)
			{
				$MTC->Text($Plugin)->Send();
			} */
		}

		# Unload Plugin(s)

		return PLUGIN_HANDLED;
	}

	// Version
	public function cmdVersion($cmd, $ucid)
	{
		IS_MTC()->UCID($ucid)->Text('PRISM Version ^7' . PHPInSimMod::VERSION)->Send();
		return PLUGIN_HANDLED;
	}

	// Admins
	public function cmdAdminList($cmd, $ucid)
	{
		global $PRISM;

		$MTC = IS_MTC()->UCID($ucid)->Text('Admins detailed to this server:')->Send();

		foreach ($PRISM->admins->getAdminsInfo() as $user => $details)
			$MTC->Text("    $user")->Send();

		return PLUGIN_HANDLED;
	}
}
?>