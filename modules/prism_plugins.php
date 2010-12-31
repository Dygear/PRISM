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
				if (isset($details['useHosts']))
					$this->pluginvars[$pluginID]['useHosts'] = explode(',', $details['useHosts']);
				else
					unset($this->pluginvars[$pluginID]);
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
		
		return TRUE;
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

		# If there are no plugins, then don't loop through the list.
		if ($this->pluginvars == NULL)
			return TRUE;

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
			# If the packet we are looking at has no callbacks for this packet type don't go to the loop.
			if (!isset($plugin->callbacks[$packet->Type]))
				continue;

			# If the plugin is not registered on this server, skip this plugin.
			if (!$this->isPluginEligibleForPacket($name, $hostID))
				continue;

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
			if ($this->canUserAccessCommand($this->getClientByUCID($packet->UCID)->UName, $callback))
				$this->$callback['method']($cmdString, $packet->UCID, $packet);
			else
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
		}
		else if ($packet->UserType == MSO_O AND
			$callback = $this->getCallback($this->localCommands, $packet->Msg) AND
			$callback !== FALSE
		) {
			if ($this->canUserAccessCommand($this->getClientByUCID($packet->UCID)->UName, $callback))
				$this->$callback['method']($packet->Msg, $packet->UCID, $packet);
			else
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
		}
	}
	// This is the yang to the registerInsimCommand function's Yin.
	public function handleInsimCmd(IS_III $packet)
	{
		if ($callback = $this->getCallback($this->insimCommands, $packet->Msg) && $callback !== FALSE)
		{
			if ($this->canUserAccessCommand($this->getClientByUCID($packet->UCID)->UName, $callback))
				$this->$callback['method']($packet->Msg, $packet->UCID, $packet);
			else
				console("{$this->getClientByUCID($packet->UCID)->UName} tried to access {$callback['method']}.");
		}
	}
	// This is the yang to the registerConsoleCommand function's Yin.
	public function handleConsoleCmd($string)
	{
		if ($callback = $this->getCallback($this->consoleCommands, $string) && $callback !== FALSE)
		{
			$this->$callback['method']($string, NULL);
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
	protected function registerCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		$this->registerInsimCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerLocalCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
		$this->registerSayCommand($cmd, $callbackMethod, $info, $defaultAdminLevelToAccess);
	}
	// Any command that comes from the PRISM console. (STDIN)
	protected function registerConsoleCommand($cmd, $callbackMethod, $info = '')
	{
		if (!isset($this->callbacks['STDIN']) && !isset($this->callbacks['STDIN']['handleConsoleCmd']))
		{	# We don't have any local callback hooking to the STDIN stream, make one.
			$this->registerPacket('handleInsimCmd', 'STDIN');
		}
		$this->consoleCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info);
	}
	// Any command that comes from the "/i" type. (III)
	protected function registerInsimCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_III]) && !isset($this->callbacks[ISP_III]['handleInsimCmd']))
		{	# We don't have any local callback hooking to the ISP_III packet, make one.
			$this->registerPacket('handleInsimCmd', ISP_III);
		}
		$this->insimCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
	// Any command that comes from the "/o" type. (MSO->Flags = MSO_O)
	protected function registerLocalCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
		$this->localCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'accessLevel' => $defaultAdminLevelToAccess);
	}
	// Any say event with prefix charater (ISI->Prefix) with this command type. (MSO->Flags = MSO_PREFIX)
	protected function registerSayCommand($cmd, $callbackMethod, $info = '', $defaultAdminLevelToAccess = -1)
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
	protected function &getPlayerByUCID(&$UCID, $hostID = NULL)
	{
		$return = NULL;
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== NULL && isset($clients[$UCID]))
			return $clients[$UCID]->players;
		return $return;
	}
	protected function &getPlayerByPName(&$PName, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL)
		{
			foreach ($players as $plid => $player)
			{
				if ($player->PName == $PName)
					return $player;
			}
		}
		return $return;
	}
	protected function &getPlayerByUName(&$UName, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL)
		{
			foreach ($players as $plid => $player)
			{
				if ($player->UName == $UName)
					return $player;
			}
		}
		return $return;
	}
	protected function &getClientByPLID(&$PLID, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL && isset($players[$PLID]))
		{
			$UCID = $players[$PLID]->UCID; # As so to avoid Indirect modification of overloaded property NOTICE;
			return $this->getClientByUCID($UCID);
		}
		return $return;
	}
	protected function &getClientByUCID(&$UCID, $hostID = NULL)
	{
		$return = NULL;
		if (($clients =& $this->getHostState($hostID)->clients) && $clients !== NULL && isset($clients[$UCID]))
			return $clients[$UCID];
		return $return;
	}
	protected function &getClientByPName(&$PName, $hostID = NULL)
	{
		$return = NULL;
		if (($players = $this->getHostState($hostID)->players) && $players !== NULL)
		{
			foreach ($players as $plid => $player)
			{
				if ($player->PName == $PName)
				{
					$UCID = $player->UCID; # As so to avoid Indirect modification of overloaded property NOTICE;
					return $this->getClientByUCID($UCID);
				}
			}
		}
		return $return;
	}
	protected function &getClientByUName(&$UName, $hostID = NULL)
	{
		$return = NULL;
		if (($clients = $this->getHostState($hostID)->clients) && $clients !== NULL)
		{
			foreach ($clients as $ucid => $client)
			{
				if ($client->UName == $UName)
					return $client;
			}
		}
		return $return;
	}
	// Is
	/**
	 * @parm $x - A IS_MCI->CompCar->X
	 * @parm $y - A IS_MCI->CompCar->Y
	 * @parm $polygon - A array of X, Y points.
	 * @author PHP version by filur & Dygear
	 * @coauthor Original code by Brian J. Fox of MetaHTML.
	 */
	public function isInPoly($x, $y, array $vertices)
	{
		$lines_crossed = 0;
		foreach ($vertices as $index => $cVertex)
		{
			if ($index == 0) continue;

			$lVertex =& $vertices[$index - 1];

			$min_x = min($lVertex['x'], $cVertex['x']);
			$max_x = max($lVertex['x'], $cVertex['x']);
			$min_y = min($lVertex['y'], $cVertex['y']);
			$max_y = max($lVertex['y'], $cVertex['y']);

			if ($x < $min_x || $x > $max_x || $y < $min_y || $y > $max_y)
			{
				if ($x < $min_x && $y > $min_y && $y < $max_y)
					++$lines_crossed;
				continue;
			}

			$slope = ($lVertex['y'] - $cVertex['y']) / ($lVertex['x'] - $cVertex['x']);
			if ((($y - ($lVertex['y'] - ($slope * $lVertex['x']))) / $slope) >= $x)
				++$lines_crossed;
		}
		return ($lines_crossed % 2) ? TRUE : FALSE;
	}
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

	// Timers
	const TIMER_CLOSE = 0; /** Timer will run once, the default behavior. */
	const TIMER_REPEAT = 1; /** Timer will repeat until it returns PLUGIN_STOP. */
	const TIMER_NOTRACK = 2; /** Timer will not carry over through track changes. */

	public $timers = array();	# Array of timers.
	public $timeout = NULL;		# When the next timeout is, read only from outside of this class.

	/** Timed Callbacks. */
	// Registers a callback method.
	protected function createTimer($interval = 1.0, $callback, $args = NULL, $flags = Plugins::TIMER_CLOSE)
	{
		# This will be the time when this timer is to trigger.
		$timestamp = microtime(TRUE) + $interval;

		# Check is this timestamp is unused, makes a new one if one is not present.
		if (!isset($this->timers["$timestamp"]))
			$this->timers["$timestamp"] = array();

		# Adds our timer to the array.
		$this->timers["$timestamp"][$callback] = array('interval' => $interval, 'args' => $args, 'flags' => $flags);

		$this->getTimeout($timestamp);

		# Return our timestamp.
		return $timestamp;
	}
	// Check to see if this timeout is less then our known next timeout.
	protected function getTimeout($timestamp = NULL)
	{
		if ($timestamp !== NULL AND $this->timeout > $timestamp)
			$this->timeout = $timestamp;
		return $this->timeout;
	}
	// Sort the array to make sure the next timer (smallest float) is on the top of the list.
	protected function sortTimers()
	{
		return ksort($this->timers);
	}
	// Executes the elapsed timers, and returns when the next timer should execute or NULL if no timers are left.
	public function executeTimers()
	{
		$timeNow = microtime(TRUE);
		if (!empty($this->timers))
		{
			foreach ($this->timers as $timestamp => $timer)
			{
				# Check to see if the first timer (next to be executed) has elpased.
				if ($timeNow < $timestamp)
					continue; # We continue as we don't have, or no longer have, any elpased timers.
				# Here we execute elapsed timers.
				foreach ($this->timers[$timestamp] as $callback => $info)
				{
					$return = $this->$callback($info['args']);
					if ($info['flags'] == Plugins::TIMER_CLOSE OR $return == PLUGIN_STOP)
					{
						unset($this->timers[$timestamp][$callback]);

						if (count($this->timers[$timestamp]) == 0)
							unset($this->timers[$timestamp]);

						if ($timestamp > $this->timeout)
							$this->timeout = NULL;
					}
					else if ($timer['flags'] == Plugins::TIMER_REPEAT)
						$this->createTimer($info['interval'], $info['args'], $info['flags']);
				}
				$this->sortTimers();
			}
		}
		return NULL;
	}
}

?>