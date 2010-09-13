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

	public function initialise()
	{
		global $PRISM;
		
		$this->pluginvars = array();
		
		if ($this->loadIniFile($this->pluginvars, 'plugins.ini'))
		{
			foreach ($this->pluginvars as $pluginID => $v)
			{
				if (!is_array($v))
				{
					console('Section error in plugins.ini file!');
					return FALSE;
				}
			}
			if ($PRISM->config->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded plugins.ini');

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
			Interactive::queryPlugins($this->pluginvars, $this->connvars);

			if ($this->createIniFile('plugins.ini', 'PHPInSimMod Plugins', $this->pluginvars))
				console('Generated config/plugins.ini');

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

	/** Construct */
	public function __construct(&$parent)
	{
		$this->parent =& $parent;
	}
	protected function sendPacket($packetClass)
	{
		global $PRISM;
		return $PRISM->hosts->sendPacket($packetClass);
	}

	/** Parse Methods */
	public function readFlags($flagsString = '')
	{
		//
		// I HAVE MOVED THIS TO prism_functions.php
		// where it is named flagsToInteger() (and there is a prototype for its counterpart, flagsToString()).
		//

		# We don't have anything to parse.
		if ($flagsString == '')
			return FALSE;

		$flagsBitwise = 0;
		for ($chrPointer = 0, $strLen = strlen($flagsString); $chrPointer < $strLen; ++$chrPointer)
		{
			# Convert this charater to it's ASCII int value.
			$char = ord($flagsString{$chrPointer});

			# We only want a (ASCII = 97) through z (ASCII 122), nothing else.
			if ($char < 97 || $char > 122)
				continue;

			# Check we have already set that flag, if so skip it!
			if ($flagsBitwise & (1 << ($char - 97)))
				continue;

			# Add the value to our $flagBitwise intager.
			$flagsBitwise += (1 << ($char - 97));
		}
		return $flagsBitwise;
	}

	/** Handle Methods */
	// This is the yang to the registerSayCommand & registerLocalCommand function's Yin.
	public function handleCmd(IS_MSO $packet)
	{
		if ($packet->UserType == MSO_PREFIX)
			$CMD = substr($packet->Msg, $packet->TextStart);
		else if ($packet->UserType == MSO_O)
			$CMD = $packet->Msg;
		else
			return;
		
		if ($packet->UserType & MSO_PREFIX AND isset($this->sayCommands[$CMD]))
		{
			$method = $this->sayCommands[$CMD]['method'];
			$this->$method($CMD, $packet->PLID, $packet->UCID, $packet);
		}
		else if ($packet->UserType == MSO_O AND isset($this->localCommands[$CMD]))
		{
			var_dump($CMD);
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
		$args = func_get_args();
		for ($i = 2, $j = count($args); $i < $j; ++$i)
			$this->callbacks[$args[$i]][] = $callbackMethod;
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
		return $this->parent->hosts->curHostID;
	}
	
	/** PRISM Methods */
	protected function prismGetPlugins()
	{
		return $this->parent->plugins;
	}
}

?>