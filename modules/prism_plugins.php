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
	public $insimCommands = array();
	public $localCommands = array();
	public $sayCommands = array();

	/** Send Methods */
	protected function sendPacket($packetClass)
	{
		global $PRISM;
		return $PRISM->hosts->sendPacket($packetClass);
	}

	/** Handle Methods */
	// This is the yang to the registerSayCommand & registerLocalCommand function's Yin.
	public function handleCmd(IS_MSO $packet)
	{
		if ($packet->UserType == MSO_PREFIX)
			$CMD = substr($packet->Msg, $packet->TextStart + 1);
		else if ($packet->UserType == MSO_O)
			$CMD = $packet->Msg;
		else
			return;

		if ($packet->UserType == MSO_PREFIX AND isset($this->sayCommands[$CMD]))
		{
			$method = $this->sayCommands[$CMD]['method'];
			$this->$method($CMD, $packet->PLID, $packet->UCID, $packet);
		}
		else if ($packet->UserType == MSO_O AND isset($this->localCommands[$CMD]))
		{
			$method = $this->localCommands[$CMD]['method'];
			$this->$method($CMD, $packet->PLID, $packet->UCID, $packet);
		}
	}
	// This is the yang to the registerInsimCommand function's Yin.
	public function handleInsimCmd(IS_III $packet)
	{
		if (isset($this->insimCommands[$packet->Msg]))
		{
			$method = $this->insimCommands[$packet->Msg]['method'];
			$this->$method($packet->Msg, $packet->PLID, $packet->UCID, $packet);
		}
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
	protected function registerConsoleCommand($cmd, $callbackMethod, $info = "") {}
	// Any command that comes from the "/i" type. (III)
	protected function registerInsimCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_III]) && !isset($this->callbacks[ISP_III]['handleInsimCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleInsimCmd', ISP_III);
		}
		$this->insimCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'access' => $defaultAdminLevelToAccess);
	}
	// Any command that comes from the "/o" type. (MSO->Flags = MSO_O)
	protected function registerLocalCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
		$this->localCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'access' => $defaultAdminLevelToAccess);
	}
	// Any say event with prefix charater (ISI->Prefix) with this command type. (MSO->Flags = MSO_PREFIX)
	protected function registerSayCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1)
	{
		if (!isset($this->callbacks[ISP_MSO]) && !isset($this->callbacks[ISP_MSO]['handleCmd']))
		{	# We don't have any local callback hooking to the ISP_MSO packet, make one.
			$this->registerPacket('handleCmd', ISP_MSO);
		}
		$this->sayCommands[$cmd] = array('method' => $callbackMethod, 'info' => $info, 'access' => $defaultAdminLevelToAccess);
	}

	/** Server Methods */
	protected function serverGetName()
	{
		return $this->parent->hosts[$this->parent->hosts->curHostID]->HName;
	}
	
	/** Is Methods */
	# Admins
	protected function isAdmin(&$username, $hostID = NULL)
	{
		global $PRISM;
		# Check the user is defined as an admin.
		if (!$PRISM->admins->adminExists($username))
			return FALSE;

		# set the $hostID;
		if ($hostID === NULL)
			$hostID = $PRISM->hosts->curHostID;

		# Check the user is defined as an admin on all or the host current host.
		$adminInfo = $PRISM->admins->getAdminInfo($username);
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

	protected function isAdminImmune(&$username)
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