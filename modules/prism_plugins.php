<?php
/**
 * PHPInSimMod - Plugin Module
 * @package PRISM
 * @subpackage Plugin
*/

class PluginHandler extends SectionHandler
{
	private $plugins			= array();			# Stores references to the plugins we've spawned.
	private $pluginvars			= array();

	public function __construct()
	{
		$this->iniFile = 'plugins.ini';
	}
	
	public function initialise()
	{
		global $PRISM;
		
		$this->pluginvars = array();
		
		if ($this->loadIniFile($this->pluginvars))
		{
			foreach ($this->pluginvars as $pluginID => $v)
			{
				if (!is_array($v))
				{
					console('Section error in '.$this->iniFile.' file!');
					return FALSE;
				}
			}
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded '.$this->iniFile);

			// Parse useHosts values of plugins
			foreach ($this->pluginvars as $pluginID => $details)
			{
				$this->pluginvars[$pluginID]['useHosts'] = explode(',', $details['useHosts']);
			}
		}
		else
		{
			# We ask the client to manually input the plugin details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryPlugins($this->pluginvars, $PRISM->hosts->getHostsInfo());

			if ($this->createIniFile('PHPInSimMod Plugins', $this->pluginvars))
				console('Generated config/'.$this->iniFile);

			// Parse useHosts values of plugins
			foreach ($this->pluginvars as $pluginID => $details)
			{
				$this->pluginvars[$pluginID]['useHosts'] = explode('","', $details['useHosts']);
			}
		}
		
		return true;
	}

	public function loadPlugins()
	{
		global $PRISM;
		
		$loadedPluginCount = 0;
		
		if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
			console('Loading plugins');
		
		$pluginPath = ROOTPATH.'/plugins';
		
		if (($pluginFiles = get_dir_structure($pluginPath, FALSE, '.php')) === NULL)
		{
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('No plugins found in the directory.');
			# As we can't find any plugin files, we invalidate the the ini settings.
			$this->pluginvars = NULL;
		}
		
		# Find what plugin files have ini entrys
		foreach ($this->pluginvars as $pluginSection => $pluginHosts)
		{
			$pluginFileHasPluginSection = FALSE;
			foreach ($pluginFiles as $pluginFile)
			{
				if ("$pluginSection.php" == $pluginFile)
				{
					$pluginFileHasPluginSection = TRUE;
				}
			}
			# Remove any pluginini value who does not have a file associated with it.
			if ($pluginFileHasPluginSection === FALSE)
			{
				unset($this->pluginvars[$pluginSection]);
				continue;
			}
			# Load the plugin file.
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console("Loading plugin: $pluginSection");
			
			include_once("$pluginPath/$pluginSection.php");
			
			$this->plugins[$pluginSection] = new $pluginSection($this);
			
			++$loadedPluginCount;
		}
		
		return $loadedPluginCount;
	}
	
	public function getPlugins()
	{
		return $this->plugins;
	}
	
	private function isPluginEligibleForPacket(&$name, &$hostID)
	{
		foreach ($this->pluginvars[$name]['useHosts'] as $host)
		{
			if ($host == '*' || $host == $hostID)
				return TRUE;
		}
		return FALSE;
	}
	
	public function dispatchPacket(&$packet, &$hostID)
	{
		global $PRISM;
		
		$PRISM->hosts->curHostID = $hostID;
		foreach ($this->plugins as $name => $plugin)
		{
			if (!$this->isPluginEligibleForPacket($name, $hostID))
				continue;

			if (!isset($plugin->callbacks[$packet->Type]))
			{	# If the packet we are looking at has no callbacks for this packet type don't go to the loop.
				continue;
			}

			foreach ($plugin->callbacks[$packet->Type] as $callback)
			{
				if (($plugin->$callback($packet)) == PLUGIN_HANDLED)
					continue 2; # Skips all of the rest of the plugins who wanted this packet.
			}
		}
	}
}

abstract class Plugins
{
	/** These consts should _ALWAYS_ be defined in your classes. */
	/* const NAME;			*/
	/* const DESCRIPTION;	*/
	/* const AUTHOR;		*/
	/* const VERSION;		*/

	/** Properties */
	public $callbacks = array();
	// Callbacks
	public $consoleCommands = array();
	public $insimCommands = array();
	public $localCommands = array();
	public $sayCommands = array();

	/** Internal Methods */
	private function getCallback($cmdsArray, $cmdString)
	{
		// Quick Lookup (Commands without Args)
		if (isset($cmdsArray[$cmdString]))
			return $cmdsArray[$cmdString];

		// Through Lookup (Commands with Args)
		foreach ($cmdsArray as $cmd => $details)
		{	# Due to the nature of these commands, we have to check all instances for matches.
			if (strpos($cmdString, $cmd) === 0) # Check if the string STARTS with our command.
				return $details;
		}
		
		return FALSE;
	}

	/** Send Methods */
	protected function sendPacket(Struct $packetClass)
	{
		global $PRISM;
		return $PRISM->hosts->sendPacket($packetClass);
	}

	/** Handle Methods */
	// This is the yang to the registerSayCommand & registerLocalCommand function's Yin.
	public function handleCmd(IS_MSO $packet)
	{
		if ($packet->UserType == MSO_PREFIX AND
			$cmdString = substr($packet->Msg, $packet->TextStart + 1) AND
			$callback = $this->getCallback($this->sayCommands, $cmdString) AND
			$callback !== FALSE
		) {
			if ($this->canUserAccessCommand($this->getUserNameByUCID($packet->UCID), $callback))
				$this->$callback['method']($cmdString, $packet->PLID, $packet->UCID, $packet);
			else
				console("{$this->getUserNameByUCID($packet->UCID)} tried to access {$callback['method']}.");
		}
		else if ($packet->UserType == MSO_O AND
			$callback = $this->getCallback($this->localCommands, $packet->Msg) AND
			$callback !== FALSE
		) {
			if ($this->canUserAccessCommand($this->getUserNameByUCID($packet->UCID), $callback))
				$this->$callback['method']($packet->Msg, $packet->PLID, $packet->UCID, $packet);
			else
				console("{$this->getUserNameByUCID($packet->UCID)} tried to access {$callback['method']}.");
		}
	}
	// This is the yang to the registerInsimCommand function's Yin.
	public function handleInsimCmd(IS_III $packet)
	{
		if ($callback = $this->getCallback($this->insimCommands, $packet->Msg) && $callback !== FALSE)
		{
			if ($this->canUserAccessCommand($this->getUserNameByUCID($packet->UCID), $callback))
				$this->$callback['method']($packet->Msg, $packet->PLID, $packet->UCID, $packet);
			else
				console("{$this->getUserNameByUCID($packet->UCID)} tried to access {$callback['method']}.");
		}
	}
	// This is the yang to the registerConsoleCommand function's Yin.
	public function handleConsoleCmd($string)
	{
		if ($callback = $this->getCallback($this->consoleCommands, $string) && $callback !== FALSE)
		{
			$this->$callback['method']($packet->Msg, $packet->PLID, $packet->UCID, $packet);
		}
	}

	/** Access Level Related Functions */
	protected function canUserAccessCommand($username, $command)
	{
		global $PRISM;
		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ($command['accessLevel'] == -1 OR $command['accessLevel'] & $adminInfo['accessFlags']) ? TRUE : FALSE;
	}
	// Returns true if a user's access level is equal or greater then the required level.
	protected function checkUserLevel($userLevel, $accessLevel)
	{
		return ($userLevel & $accessLevel) ? TRUE : FALSE;
	}

	/** Register Methods */
	// Directly registers a packet to be handled by a callbackMethod within the plugin.
	protected function registerPacket($callbackMethod, $PacketType)
	{
		$this->callbacks[$PacketType][] = $callbackMethod;
		$PacketTypes = func_get_args();
		for ($i = 2, $j = count($PacketTypes); $i < $j; ++$i)
			$this->callbacks[$PacketTypes[$i]][] = $callbackMethod;
	}

	// Setup the callbackMethod trigger to accapt a command that could come from anywhere.
	protected function registerCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		$this->registerInsimCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerLocalCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerSayCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
	}
	// Any command that comes from the PRISM console. (STDIN)
	protected function registerConsoleCommand($cmd, $callbackMethod, $info = "")
	{
		if (!isset($this->callbacks['STDIN']) && !isset($this->callbacks['STDIN']['handleConsoleCmd']))
		{	# We don't have any local callback hooking to the STDIN stream, make one.
			$this->registerPacket('handleInsimCmd', 'STDIN');
		}
		$this->consoleCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info);
	}
	// Any command that comes from the "/i" type. (III)
	protected function registerInsimCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_III]) && !isset($this->callbacks[ISP_III]['handleInsimCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleInsimCmd', ISP_III);
		}
		$this->insimCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
	// Any command that comes from the "/o" type. (MSO->Flags = MSO_O)
	protected function registerLocalCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
		$this->localCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
	// Any say event with prefix charater (ISI->Prefix) with this command type. (MSO->Flags = MSO_PREFIX)
	protected function registerSayCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
		$this->sayCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}

	/** Internal Functions */
	protected function getCurrentHostId()
	{
		global $PRISM;
		return $PRISM->hosts->curHostID;
	}
	protected function getHostId($hostID = NULL)
	{
		if ($hostID === NULL)
			return $this->getCurrentHostId();
		return $hostID;
	}
	protected function getHostInfo($hostID = NULL)
	{
		global $PRISM;
		if (($host = $PRISM->hosts->getHostById($hostID)) && $host !== NULL)
			return $host;
		return NULL;
	}
	protected function getHostState($hostID = NULL)
	{
		global $PRISM;
		if (($state = $PRISM->hosts->getStateById($hostID)) && $state !== NULL)
			return $state;
		return NULL;
	}

	/** Server Methods */
	protected function serverGetName()
	{
		if ($this->getHostState() !== NULL)
			return $this->getHostState()->HName;
		return NULL;
	}
	
	/** Client & Player */
	protected function &getPlayerByPLID(&$PLID, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL && isset($players[$PLID]))
			return $players[$PLID];
		return $return;
	}

	protected function &getClientByPLID(&$PLID, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL && isset($players[$PLID]))
		{
			$UCID = $players[$PLID]->UCID;
			return $this->getClientByUCID($UCID);
		}
		return $return;
	}

	protected function &getPlayersByUCID(&$UCID, $hostID = NULL)
	{
		$return = NULL;
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== NULL && isset($clients[$UCID]))
			return $clients[$UCID]->players;
		return $return;
	}
	
	protected function &getClientByUCID(&$UCID, $hostID = NULL)
	{
		$return = NULL;
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== NULL && isset($clients[$UCID]))
			return $clients[$UCID];
		return $return;
	}

	protected function getUserNameByUCID(&$UCID, $hostID = NULL)
	{
		$client = $this->getClientByUCID($UCID, $hostID);
		return ($client === NULL) ? FALSE : $client->UName;
	}

	protected function getUserNameByPLID(&$PLID, $hostID = NULL)
	{
		$player = $this->getPlayerByPLID($PLID, $hostID);
		if ($player === NULL)
			return FALSE;
		$UCID = $player->UCID; # if I don't do this, I get an "Indirect modification of overloaded property" notice.
		return $this->userGetUserNameByUCID($UCID);
	}

	protected function userGetPlayerNameByUCID(&$UCID, $hostID = NULL)
	{
		$client = $this->userGetByUCID($UCID, $hostID);
		return ($client === NULL) ? FALSE : $client->PName;
	}

	protected function userGetPlayerNameByPLID(&$PLID, $hostID = NULL)
	{
		$player = $this->userGetByPLID($PLID, $hostID);
		return ($player === NULL) ? FALSE : $player->PName;
	}

	// Is
	protected function isHost(&$username, $hostID = NULL)
	{
		return ($this->getHostState($this->getHostId($hostID))->clients[0]->UName == $username) ? TRUE : FALSE;
	}

	protected function isAdmin(&$username, $hostID = NULL)
	{
//		global $PRISM;
		# Check the user is defined as an admin.
//		if (!$PRISM->admins->adminExists($username))
//			return FALSE;

		# set the $hostID;
		if ($hostID === NULL)
			$hostID = $this->getHostId($hostID);

		# Check the user is defined as an admin on all or the host current host.
//		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ($this->isAdminGlobal($username) || $this->isAdminLocal($username, $hostID)) ? TRUE : FALSE;
	}
	
	protected function isAdminGlobal(&$username)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username))
			return FALSE;

		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return (strpos($adminInfo['connection'], '*') !== FALSE) ? TRUE : FALSE;
	}

	protected function isAdminLocal(&$username, $hostID = NULL)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username))
			return FALSE;

		# set the $hostID;
		if ($hostID === NULL)
			$hostID = $PRISM->hosts->curHostID;

		# Check the user is defined as an admin on the host current host.
		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ((strpos($adminInfo['connection'], $hostID) !== FALSE) !== FALSE) ? TRUE : FALSE;
	}

	protected function isImmune(&$username)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username))
			return FALSE;

		# Check the user is defined as an admin on the host current host.
		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ($adminInfo['accessFlags'] & ADMIN_IMMUNITY) ? TRUE : FALSE;
	}
}

?>