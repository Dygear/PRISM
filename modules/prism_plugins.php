<?php
/**
 * PHPInSimMod - Plugin Module
 * @package PRISM
 * @subpackage Plugin
*/

define('PRINT_CHAT',		(1 << 0));		# 1
define('PRINT_RCM',			(1 << 1));		# 2
define('PRINT_NUM',			(1 << 2)-1);	# 4 - 1
define('PRINT_CONTEXT',		PRINT_NUM);		# 3

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
		
		if ($this->loadIniFile($this->pluginvars)) {
			foreach ($this->pluginvars as $pluginID => $v) {
				if (!is_array($v)) {
					console('Section error in '.$this->iniFile.' file!');
					return false;
				}
			}
            
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console('Loaded '.$this->iniFile);
			}

			// Parse useHosts values of plugins
			foreach ($this->pluginvars as $pluginID => $details) {
				if (isset($details['useHosts'])) {
					$this->pluginvars[$pluginID]['useHosts'] = explode(',', $details['useHosts']);
				} else {
					unset($this->pluginvars[$pluginID]);
				}
			}
		} else {
			# We ask the client to manually input the plugin details here.
			require_once(ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryPlugins($this->pluginvars, $PRISM->hosts->getHostsInfo());

			if ($this->createIniFile('PHPInSimMod Plugins', $this->pluginvars)) {
				console('Generated config/'.$this->iniFile);
			}

			// Parse useHosts values of plugins
			foreach ($this->pluginvars as $pluginID => $details) {
				$this->pluginvars[$pluginID]['useHosts'] = explode('","', $details['useHosts']);
			}
		}
		
		return true;
	}

	public function loadPlugins()
	{
		global $PRISM;
		
		$loadedPluginCount = 0;
		
		if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
			console('Loading plugins');
		}
		
		$pluginPath = ROOTPATH.'/plugins';
		
		if (($pluginFiles = get_dir_structure($pluginPath, false, '.php')) === null) {
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console('No plugins found in the directory.');
			}
            
			# As we can't find any plugin files, we invalidate the the ini settings.
			$this->pluginvars = null;
		}

		# If there are no plugins, then don't loop through the list.
		if ($this->pluginvars == null) {
			return true;
		}

		# Find what plugin files have ini entrys
		foreach ($this->pluginvars as $pluginSection => $pluginHosts) {
			$pluginFileHasPluginSection = false;
            
			foreach ($pluginFiles as $pluginFile) {
				if ("$pluginSection.php" == $pluginFile) {
					$pluginFileHasPluginSection = true;
				}
			}
            
			# Remove any pluginini value who does not have a file associated with it.
			if ($pluginFileHasPluginSection === false) {
				unset($this->pluginvars[$pluginSection]);
				continue;
			}
            
			# Load the plugin file.
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
				console("Loading plugin: $pluginSection");
			}
			
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
		foreach ($this->pluginvars[$name]['useHosts'] as $host) {
			if ($host == '*' || $host == $hostID) {
				return true;
			}
		}
        
		return false;
	}
	
	public function dispatchPacket(&$packet, &$hostID)
	{
		global $PRISM;
		
		$PRISM->hosts->curHostID = $hostID;
        
		foreach ($this->plugins as $name => $plugin) {
			# If the packet we are looking at has no callbacks for this packet type don't go to the loop.
			if (!isset($plugin->callbacks[$packet->Type])) {
				continue;
			}

			# If the plugin is not registered on this server, skip this plugin.
			if (!$this->isPluginEligibleForPacket($name, $hostID)) {
				continue;
			}

			foreach ($plugin->callbacks[$packet->Type] as $callback) {
				if (($plugin->$callback($packet)) == PLUGIN_HANDLED) {
					continue 2; # Skips all of the rest of the plugins who wanted this packet.
				}
			}
		}
	}
}

abstract class Plugins extends Timers
{
	/** These consts should _ALWAYS_ be defined in your classes. */
	/* const NAME;			*/
	/* const DESCRIPTION;	*/
	/* const AUTHOR;		*/
	/* const VERSION;		*/

	/** Properties */
	public $callbacks = array(
	);
	// Callbacks
	public $consoleCommands = array();
	public $insimCommands = array();
	public $localCommands = array();
	public $sayCommands = array();

	/** Internal Methods */
	private function getCallback($cmdsArray, $cmdString)
	{
		// Quick Lookup (Commands without Args)
		if (isset($cmdsArray[$cmdString])) {
			return $cmdsArray[$cmdString];
		}

		// Through Lookup (Commands with Args)
		foreach ($cmdsArray as $cmd => $details)  {
            # Due to the nature of these commands, we have to check all instances for matches.
			if (strpos($cmdString, $cmd) === 0) { # Check if the string STARTS with our command.
				return $details;
			}
		}
		
		return false;
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
		if ($packet->UserType == MSO_PREFIX && $cmdString = substr($packet->Msg, $packet->TextStart + 1) && $callback = $this->getCallback($this->sayCommands, $cmdString) && $callback !== false) {
			if ($this->canUserAccessCommand($packet->UCID, $callback)) {
				$this->$callback['method']($cmdString, $packet->UCID, $packet);
			} else {
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
			}
		} else if ($packet->UserType == MSO_O && $callback = $this->getCallback($this->localCommands, $packet->Msg) && $callback !== false) {
			if ($this->canUserAccessCommand($packet->UCID, $callback)) {
				$this->$callback['method']($packet->Msg, $packet->UCID, $packet);
			} else {
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
			}
		}
	}
    
	// This is the yang to the registerInsimCommand function's Yin.
	public function handleInsimCmd(IS_III $packet)
	{
		if ($callback = $this->getCallback($this->insimCommands, $packet->Msg) && $callback !== false) {
			if ($this->canUserAccessCommand($packet->UCID, $callback)) {
				$this->$callback['method']($packet->Msg, $packet->UCID, $packet);
			} else {
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
			}
		}
	}
    
	// This is the yang to the registerConsoleCommand function's Yin.
	public function handleConsoleCmd($string)
	{
		if ($callback = $this->getCallback($this->consoleCommands, $string) && $callback !== false) {
			$this->$callback['method']($string, null);
		}
	}

	/** Access Level Related Functions */
	protected function canUserAccessCommand($UCID, $cmd)
	{
		# Hosts are automatic admins so due to their nature, they have full access.
		# Commands that have no premission level don't require this check.
		if ($UCID == 0 OR $cmd['accessLevel'] == -1) {
			return true;
		}

		global $PRISM;
		$adminInfo = $PRISM->admins->getAdminInfo($this->getClientByUCID($UCID)->UName);
		return ($cmd['accessLevel'] & $adminInfo['accessFlags']) ? true : false;
	}
    
	// Returns true if a user's access level is equal or greater then the required level.
	protected function checkUserLevel($userLevel, $accessLevel)
	{
		return ($userLevel & $accessLevel) ? true : false;
	}

	/** Register Methods */
	// Directly registers a packet to be handled by a callbackMethod within the plugin.
	protected function registerPacket($callbackMethod, $PacketType)
	{
		$this->callbacks[$PacketType][] = $callbackMethod;
		$PacketTypes = func_get_args();
        
		for ($i = 2, $j = count($PacketTypes); $i < $j; ++$i) {
			$this->callbacks[$PacketTypes[$i]][] = $callbackMethod;
		}
	}

	// Setup the callbackMethod trigger to accapt a command that could come from anywhere.
	protected function registerCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		$this->registerInsimCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerLocalCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerSayCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
	}
    
	// Any command that comes from the PRISM console. (STDIN)
	protected function registerConsoleCommand($cmd, $callbackMethod, $info = '')
	{
		if (!isset($this->callbacks['STDIN']) && !isset($this->callbacks['STDIN']['handleConsoleCmd'])) {
            # We don't have any local callback hooking to the STDIN stream, make one.
			$this->registerPacket('handleInsimCmd', 'STDIN');
		}
        
	    $this->consoleCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info);
	}
    
	// Any command that comes from the "/i" type. (III)
	protected function registerInsimCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_III]) && !isset($this->callbacks[ISP_III]['handleInsimCmd'])) {
            # We don't have any local callback hooking to the ISP_III packet, make one.
			$this->registerPacket('handleInsimCmd', ISP_III);
		}
        
		$this->insimCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
    
	// Any command that comes from the "/o" type. (MSO->Flags = MSO_O)
	protected function registerLocalCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd'])) {
            # We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
        
		$this->localCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
    
	// Any say event with prefix charater (ISI->Prefix) with this command type. (MSO->Flags = MSO_PREFIX)
	protected function registerSayCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd'])) {
            # We don't have any local callback hooking to the ISP_MSO packet, make one.
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
    
	protected function getHostId($hostID = null)
	{
		if ($hostID === null) {
			return $this->getCurrentHostId();
		}
        
		return $hostID;
	}
    
	protected function getHostInfo($hostID = null)
	{
		global $PRISM;
        
		if (($host = $PRISM->hosts->getHostById($hostID)) && $host !== null) {
			return $host;
		}
        
		return null;
	}
    
	protected function getHostState($hostID = null)
	{
		global $PRISM;
        
		if (($state = $PRISM->hosts->getStateById($hostID)) && $state !== null) {
			return $state;
		}
        
		return null;
	}

	/** Server Methods */
	protected function serverGetName()
	{
		if ($this->getHostState() !== null) {
			return $this->getHostState()->HName;
		}
        
		return null;
	}
	
	/** Client & Player */
	protected function &getPlayerByPLID(&$PLID, $hostID = null)
	{        
		if (($players = $this->getHostState($hostID)->players) && $players !== null && isset($players[$PLID])) {
			return $players[$PLID];
		}
        
		return null;
	}
    
	protected function &getPlayerByUCID(&$UCID, $hostID = null)
	{
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== null && isset($clients[$UCID])) {
			return $clients[$UCID]->players;
        }
    
		return null;
	}
    
	protected function &getPlayerByPName(&$PName, $hostID = null)
	{
		if (($players = $this->getHostState($hostID)->players) && $players !== null) {
			foreach ($players as $plid => $player) {
				if (strToLower($player->PName) == strToLower($PName)) {
					return $player;
				}
			}
		}
        
		return null;
	}
    
	protected function &getPlayerByUName(&$UName, $hostID = null)
	{
		if (($players = $this->getHostState($hostID)->players) && $players !== null) {
			foreach ($players as $plid => $player) {
				if (strToLower($player->UName) == strToLower($UName)) {
					return $player;
				}
			}
		}
        
		return null;
	}
    
	protected function &getClientByPLID(&$PLID, $hostID = null)
	{
		if (($players = $this->getHostState($hostID)->players) && $players !== null && isset($players[$PLID])) {
			$UCID = $players[$PLID]->UCID; # As so to avoid Indirect modification of overloaded property NOTICE;
			return $this->getClientByUCID($UCID);
		}
        
		return $return;
	}
    
	protected function &getClientByUCID(&$UCID, $hostID = null)
	{
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== null && isset($clients[$UCID])) {
			return $clients[$UCID];
		}
        
		return null;
	}
    
	protected function &getClientByPName(&$PName, $hostID = null)
	{
		if (($players = $this->getHostState($hostID)->players) && $players !== null) {
			foreach ($players as $plid => $player) {
				if (strToLower($player->PName) == ($PName)) {
					$UCID = $player->UCID; # As so to avoid Indirect modification of overloaded property NOTICE;
					return $this->getClientByUCID($UCID);
				}
			}
		}
        
		return null;
	}
    
	protected function &getClientByUName(&$UName, $hostID = null)
	{
		if (($clients = $this->getHostState($hostID)->clients) && $clients !== null) {
			foreach ($clients as $ucid => $client) {
				if (strToLower($client->UName) == strToLower($UName)) {
					return $client;
				}
			}
		}
        
		return null;
	}
    
	// Is
	protected function isHost(&$username, $hostID = null)
	{
		return ($this->getHostState($this->getHostId($hostID))->clients[0]->UName == $username) ? true : false;
	}

	protected function isAdmin(&$username, $hostID = null)
	{
//		global $PRISM;
		# Check the user is defined as an admin.
//		if (!$PRISM->admins->adminExists($username))
//			return false;

		# set the $hostID;
		if ($hostID === null) {
			$hostID = $this->getHostId($hostID);
		}

		# Check the user is defined as an admin on all or the host current host.
//		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ($this->isAdminGlobal($username) || $this->isAdminLocal($username, $hostID)) ? true : false;
	}
	
	protected function isAdminGlobal(&$username)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username)) {
			return false;
		}

		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return (strpos($adminInfo['connection'], '*') !== false) ? true : false;
	}

	protected function isAdminLocal(&$username, $hostID = null)
	{
		global $PRISM;
        
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username)) {
			return false;
		}

		# set the $hostID;
		if ($hostID === null) {
			$hostID = $PRISM->hosts->curHostID;
		}

		# Check the user is defined as an admin on the host current host.
		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ((strpos($adminInfo['connection'], $hostID) !== false) !== false) ? true : false;
	}

	protected function isImmune(&$username)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username)) {
			return false;
		}

		# Check the user is defined as an admin on the host current host.
		$adminInfo = $PRISM->admins->getAdminInfo($username);
		return ($adminInfo['accessFlags'] & ADMIN_IMMUNITY) ? true : false;
	}
}
