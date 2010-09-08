<?php
/* PHPInSimMod
*
* by the PHPInSimMod Development Team.
*
*/

/* Defines */
// PRISM
define('PRISM_DEBUG_CORE',		1);			# Shows Debug Messages From the Core
define('PRISM_DEBUG_SOCKETS',	2);			# Shows Debug Messages From the Sockets Module
define('PRISM_DEBUG_MODULES',	4);			# Shows Debug Messages From the all Modules
define('PRISM_DEBUG_PLUGINS',	8);			# Shows Debug Messages From the Plugins
define('PRISM_DEBUG_ALL',		15);		# Shows Debug Messages From All

define('MAINTENANCE_INTERVAL', 	2);			# The frequency in seconds to do connection maintenance checks.

// Return Codes: 
define('PLUGIN_CONTINUE',		0);			# Plugin passes through operation. Whatever called it continues.
define('PLUGIN_HANDLED',		1);			# Plugin halts continued operation. Plugins following in the plugins.ini won't be called.

error_reporting(E_ALL);
ini_set('display_errors',		'true');

define('ROOTPATH', dirname(realpath(__FILE__)));

// the packets and connections module are two of the three REQUIRED modules for PRISM.
require_once(ROOTPATH . '/modules/prism_packets.php');
require_once(ROOTPATH . '/modules/prism_connections.php');
require_once(ROOTPATH . '/modules/prism_plugins.php');

$PRISM = new PHPInSimMod($argc, $argv);

/**
 * PHPInSimMod
 * @package PRISM
 * @author Dygear (Mark Tomlin) <Dygear@gmail.com>
 * @author ripnet (Tom Young) <ripnet@gmail.com>
 * @author morpha (Constantin Köpplinger) <morpha@xigmo.net>
 * @author Victor (Victor van Vlaardingen) <vic@lfs.net>
*/
class PHPInSimMod
{
	const VERSION = '0.1.9';
	const ROOTPATH = ROOTPATH;

	private $isWindows		= FALSE;

	/* Run Time Arrays */
	// Config variables
	private $cvars			= array('prefix'		=> '!',
									'debugMode'		=> PRISM_DEBUG_ALL,
									'logMode'		=> 7,
									'logFileMode'	=> 3,
									'relayIP'		=> 'isrelay.lfs.net',
									'relayPort'		=> 47474,
									'relayPPS'		=> 2,
									'dateFormat'	=> 'M jS Y',
									'timeFormat'	=> 'H:i:s',
									'logFormat'		=> 'm-d-y@H:i:s',
									'logNameFormat'	=> 'Ymd',
									'httpIP'		=> '0.0.0.0',
									'httpPort'		=> '1800');
	private $connvars		= array();
	private $pluginvars		= array();

	private $hosts			= array();			# Stores references to the hosts we're connected to
	public $curHostID		= NULL;				# Contains the current HostID we are talking to. (For the plugins::sendPacket method).

	// InSim
	private $plugins		= array();			# Stores references to the plugins we've spawned.

	# Time outs
	private $sleep			= NULL;
	private $uSleep			= NULL;
	
	private $nextMaintenance= 0;

	// Main while loop will run as long as this is set to TRUE.
	private $isRunning = TRUE;

	private function loadIniFiles()
	{
		// Load generic cvars.ini
		if ($this->loadIniFile($this->cvars, 'cvars.ini', FALSE))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded cvars.ini');
		}
		else
		{
			console('Using cvars defaults.');
			if ($this->createIniFile('cvars.ini', 'PHPInSimMod Configuration Variables', array('prism' => &$this->cvars)))
				console('Generated config/cvars.ini');
		}

		// Load connections.ini
		if ($this->loadIniFile($this->connvars, 'connections.ini'))
		{
			foreach ($this->connvars as $hostID => $v)
			{
				if (!is_array($v))
				{
					console('Section error in connections.ini file!');
					return FALSE;
				}
			}
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded connections.ini');
		}
		else
		{
			# We ask the client to manually input the connection details here.
			require_once($this::ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryConnections($this->connvars);
			
			# Then build a connections.ini file based on these details provided.
			if ($this->createIniFile('connections.ini', 'InSim Connection Hosts', $this->connvars))
				console('Generated config/connections.ini');
		}

		// Load plugins.ini
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
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
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
			require_once($this::ROOTPATH . '/modules/prism_interactive.php');
			Interactive::queryPlugins($this->pluginvars, $this->connvars);

			if ($this->createIniFile('plugins.ini', 'PHPInSimMod Plugins', $this->pluginvars))
				console('Generated config/plugins.ini');

			// Parse useHosts values of plugins
			foreach ($this->pluginvars as $pluginID => $details)
			{
				$this->pluginvars[$pluginID]['useHosts'] = explode('","', $details['useHosts']);
			}
		}

		return TRUE;
	}
	
	// Generic function to load ini files into a passed variable
	// If a ini file with the name and a prefix local_ already exists it is loaded instead
	private function loadIniFile(array &$target, $iniFile, $parseSections = TRUE)
	{
		$iniVARs = FALSE;
		
		// Should parse the $PrismDir/config/***.ini file, and load them into the passed $target array.
		$iniPath = $this::ROOTPATH . '/configs/'.$iniFile;
		$localIniPath = $this::ROOTPATH . '/configs/local_'.$iniFile;
		
		if (file_exists($localIniPath))
		{
			if (($iniVARs = parse_ini_file($localIniPath, $parseSections)) === FALSE)
			{
				console('Could not parse ini file "local_'.$iniFile.'". Using global.');
			}
			else
			{
				console('Using local ini file "local_'.$iniFile.'"');
			}
		}
		if ($iniVARs === FALSE)
		{
			if (!file_exists($iniPath))
			{
				console('Could not find ini file "'.$iniFile.'"');
				return FALSE;
			}
			if (($iniVARs = parse_ini_file($iniPath, $parseSections)) === FALSE)
			{
				console('Could not parse ini file "'.$iniFile.'"');
				return FALSE;
			}
		}

		// Merge iniVARs into target (array_merge didn't seem to work - maybe because target is passed by reference?)
		foreach ($iniVARs as $k => $v)
			$target[$k] = $v;
		
		# At this point we're always successful
		return TRUE;
	}

	private function createIniFile($iniFile, $desc, array $options)
	{
		// Check if config folder exists
		if (!file_exists($this::ROOTPATH . '/configs/') && 
			!@mkdir($this::ROOTPATH . '/configs/'))
		{
			return FALSE;
		}
		
		// Check if file doesn't already exist
		if (file_exists($this::ROOTPATH . '/configs/'.$iniFile))
			return FALSE;
		
		// Generate file contents
		$text = '; '.$desc.' (automatically genereated)'.PHP_EOL;
		$text .= '; File location: ./PHPInSimMod/configs/'.$iniFile.PHP_EOL;
		$main = '';
		foreach ($options as $section => $data)
		{
			if (is_array($data))
			{
				$main .= PHP_EOL.'['.$section.']'.PHP_EOL;
				foreach ($data as $key => $value)
				{
					$main .= $key.' = '.((is_numeric($value)) ? $value : '"'.$value.'"').PHP_EOL;
				}
			}
		}

		if ($main == '')
			return FALSE;
		
		$text .= $main.PHP_EOL;
		
		// Write contents
		if (!file_put_contents($this::ROOTPATH . '/configs/'.$iniFile, $text))
			return FALSE;
		
		return TRUE;
	}
	
	private function loadPlugins()
	{
		$loadedPluginCount = 0;
		
		if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
			console('Loading plugins');
		
		$pluginPath = $this::ROOTPATH.'/plugins';
		
		if (($pluginFiles = get_dir_structure($pluginPath, FALSE, '.php')) === NULL)
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
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
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console("Loading plugin: $pluginSection");
			
			include_once("$pluginPath/$pluginSection.php");
			
			$this->plugins[$pluginSection] = new $pluginSection($this);
			
			++$loadedPluginCount;
		}
		
		return $loadedPluginCount;
	}

	// Pseudo Magic Functions
	private static function _autoload($className)
	{
		require_once(ROOTPATH . "/modules/prism_{$className}.php");
	}

	public static function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
	{
		# This error code is not included in error_reporting
		if (!(error_reporting() & $errno))
			return;

		switch ($errno)
		{
			case E_ERROR:
			case E_USER_ERROR:
					echo 'PHP ERROR:'.PHP_EOL;
					$andExit = TRUE;
				break;
			case E_WARNING:
			case E_USER_WARNING:
					echo 'PHP WARNING:'.PHP_EOL;
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
					echo 'PHP NOTICE:'.PHP_EOL;
				break;
			case E_STRICT:
					echo 'PHP STRICT:'.PHP_EOL;
				break;
			default:
					echo 'UNKNOWN:'.PHP_EOL;
				break;
		}

		echo "\t$errstr in $errfile on line $errline".PHP_EOL;

		$trace = debug_backtrace();
		foreach ($trace as $index => $call)
		{
			if ($call['function'] == 'main') break;
			if ($index > 0 && isset($call['file']) && isset($call['line']))
			{
				console("\t".$index.' :: '.$call['function'].' in '.$call['file'].':'.$call['line']);
			}
		}

		if (isset($andExit) && $andExit == TRUE)
			exit(1);

		# Don't execute PHP internal error handler
		return true;
	}

	// Real Magic Functions
	public function __construct($argc, $argv)
	{
		// This reregisters our autoload magic function into the class.
		spl_autoload_register(__CLASS__ . '::_autoload');
		set_error_handler(__CLASS__ . '::_errorHandler', E_ALL | E_STRICT);
		
		// Set the timezone
		if (isset($this->cvars['defaultTimeZone']))
			date_default_timezone_set($this->cvars['defaultTimeZone']);
		else
		{
			# I know, I'm using error suppression, but I swear it's appropriate!
			$timeZoneGuess = @date_default_timezone_get();
			date_default_timezone_set($timeZoneGuess);
			unset($timeZoneGuess);
		}
		
		// Windows OS check
		$shell = getenv('SHELL');
		if (!$shell || $shell[0] != '/')
			$this->isWindows = TRUE;
		
		// Load ini files
		if (!$this->loadIniFiles())
		{
			console('Fatal error encountered. Exiting...');
			return;
		}
		
		// Populate $this->hosts array from the connections.ini variables we've just read
		$this->populateHostsFromVars();
		
		if (
			(($pluginsLoaded = $this->loadPlugins()) == 0) &&
			($this->cvars['debugMode'] & PRISM_DEBUG_CORE))
		{
			console('No Plugins Loaded');
		} else if ($pluginsLoaded == 1) {
			console('One Plugin Loaded');
		} else {
			console("{$pluginsLoaded} Plugins Loaded.");
		}
				
		$this->nextMaintenance = time () + MAINTENANCE_INTERVAL;
		$this->main();
	}

	private function populateHostsFromVars()
	{
		$udpPortBuf = array();		// Duplicate udpPort (NLP/MCI port) value check array. Must have one socket per host to listen on.
		
		foreach ($this->connvars as $hostID => $v)
		{
			if (isset($v['useRelay']) && $v['useRelay'] > 0)
			{
				// This is a Relay connection
				$hostName		= isset($v['hostname']) ? substr($v['hostname'], 0, 31) : '';
				$adminPass		= isset($v['adminPass']) ? substr($v['adminPass'], 0, 15) : '';
				$specPass		= isset($v['specPass']) ? substr($v['specPass'], 0, 15) : '';

				// Some value checking - guess we should output some user notices here too if things go wrong.
				if ($hostName == '')
					continue;
				
				$ic				= new InsimConnection(CONNTYPE_RELAY, SOCKTYPE_TCP);
				$ic->id			= $hostID;
				$ic->ip			= $this->cvars['relayIP'];
				$ic->port		= $this->cvars['relayPort'];
				$ic->hostName	= $hostName;
				$ic->adminPass	= $adminPass;
				$ic->specPass	= $specPass;
				$ic->pps		= $this->cvars['relayPPS'];
				
				$this->hosts[$hostID] = $ic;
			}
			else
			{
				// This is a direct to host connection
				$ip				= isset($v['ip']) ? $v['ip'] : '';
				$port			= isset($v['port']) ? (int) $v['port'] : 0;
				$udpPort		= isset($v['udpPort']) ? (int) $v['udpPort'] : 0;
				$pps			= isset($v['pps']) ? (int) $v['pps'] : 3;
				$adminPass		= isset($v['password']) ? substr($v['password'], 0, 15) : '';
				$socketType		= isset($v['socketType']) ? (int) $v['socketType'] : SOCKTYPE_TCP;
				
				// Some value checking
				if ($port < 1 || $port > 65535)
				{
					console('Invalid port '.$port.' for '.$hostID);
					console('Host '.$hostID.' will be excluded.');
					continue;
				}
				if ($udpPort < 0 || $udpPort > 65535)
				{
					console('Invalid port '.$udpPort.' for '.$hostID);
					console('Host '.$hostID.' will be excluded.');
					continue;
				}
				if ($pps < 1 || $pps > 100)
				{
					console('Invalid pps '.$pps.' for '.$hostID);
					console('Host '.$hostID.' will be excluded.');
					continue;
				}
				if ($socketType != SOCKTYPE_TCP && $socketType != SOCKTYPE_UDP)
				{
					console('Invalid socket type set for '.$ip.':'.$port);
					console('Host '.$hostID.' will be excluded.');
					continue;
				}
				
				// Create new ic object
				$ic				= new InsimConnection(CONNTYPE_HOST, $socketType);
				$ic->id			= $hostID;
				$ic->ip			= $ip;
				$ic->port		= $port;
				$ic->udpPort	= $udpPort;
				$ic->pps		= $pps;
				$ic->adminPass	= $adminPass;

				if ($ic->udpPort > 0)
				{
					if (in_array($ic->udpPort, $udpPortBuf))
					{
						console('Duplicate udpPort value found! Every host must have its own unique udpPort. Not using additional port for this host.');
						$ic->udpPort = 0;
					}
					else
					{
						$udpPortBuf[] = $ic->udpPort;
						if (!$ic->createMCISocket())
						{
							console('Host '.$hostID.' will be excluded.');
							continue;
						}
					}
				}

				$this->hosts[$hostID] = $ic;
			}
		}
	}
	
	private function main()
	{
		while ($this->isRunning === TRUE)
		{
			// Setup our listen arrays
			$sockReads = array();
			$sockWrites = array();
			
			if (!$this->isWindows)
				$sockReads[] = STDIN;
			
			// Add host sockets to the arrays as needed
			// While at it, check if we need to connect to any of the hosts.
			foreach ($this->hosts as $hostID => $host)
			{
				if ($host->connStatus >= CONN_CONNECTED)
				{
						$sockReads[] = $host->socket;
						
						// If the host is lagged, we must check to see when we can write again
						if ($host->sendQStatus > 0)
							$sockWrites[] = $host->socket;
				}
				else if ($host->connStatus == CONN_CONNECTING)
				{
					$sockWrites[] = $host->socket;
				}
				else
				{
					// Should we try to connect?
					if ($host->mustConnect > -1 && $host->mustConnect < time())
					{
						if ($host->connect()) {
							$sockReads[] = $this->hosts[$hostID]->socket;
							if ($host->socketType == SOCKTYPE_TCP)
								$sockWrites[] = $this->hosts[$hostID]->socket;
						}
					}
				}
				
				// Treat secundary socketMCI separately. This socket is always open.
				if ($host->udpPort > 0 && is_resource($host->socketMCI))
					$sockReads[] = $host->socketMCI;
			}
			unset($host);
	
			$this->getSocketTimeOut();

			# Error suppressed used because this function returns a "Invalid CRT parameters detected" only on Windows.
			$numReady = @stream_select($sockReads, $sockWrites, $socketExcept = null, $this->sleep, $this->uSleep);
				
			// Keep looping until you've handled all activities on the sockets.
			while($numReady > 0)
			{
				// Host traffic
				foreach($this->hosts as $hostID => $host)
				{
					// Finalise a tcp connection?
					if ($host->connStatus == CONN_CONNECTING && 
						in_array($host->socket, $sockWrites))
					{
						$numReady--;
						
						// Check if remote replied negatively
						# Error suppressed, because of the underlying CRT (C Run Time) producing an error on Windows.
						$nr = @stream_select($r = array($host->socket), $w = null, $e = null, 0);
						if ($nr > 0)
						{
							// Experimentation showed that if something happened on this socket at this point,
							// it is always an indication that the connection failed. We close this socket now.
							$host->close();
						}
						else
						{
							// The socket has become available for writing
							$host->connectFinish();
						}
						unset($nr, $r, $w, $e);
					}

					// Recover a lagged host?
					if ($host->connStatus >= CONN_CONNECTED && 
						$host->sendQStatus > 0 &&
						in_array($host->socket, $sockWrites))
					{
						$numReady--;
						
						// Flush the sendQ and handle possible overload again
						for ($a=0; $a<$host->sendQStatus; $a++)
						{
							$bytes = $host->writeTCP($host->sendQ[$a], TRUE);
							if ($bytes == strlen($host->sendQ[$a])) {
								// an entire packet from the queue has been flushed. Remove it from the queue.
								array_shift($host->sendQ);
								$a--;

								if (--$host->sendQStatus == 0) {
									// All done flushing - reset queue variables
									$host->sendQ			= array ();
									$host->sendQTime		= 0;
									break;

								} else {
									// Set when the last packet was flushed
									$host->sendQTime		= time ();
								}
							} 
							else if ($bytes > 0)
							{
								// only partial packet sent
								$host->sendQ[$a] = substr($host->sendQ[$a], $bytes);
								break;
							}
							else
							{
								// sending queued data completely failed. We stop trying and will see if we can send more later on.
								break;
							}
						}
					}

					// Did the host send us something?
					if (in_array($host->socket, $sockReads))
					{
						$numReady--;
						$data = $packet = '';
						
						// Incoming traffic from a host
						$peerInfo = '';
						$data = $host->read($peerInfo);
						
						if (!$data)
						{
							$host->close();
						}
						else
						{
							if ($host->socketType == SOCKTYPE_UDP)
							{
								// Check that this insim packet came from the IP we connected to
								// UDP packet can be sent straight to packet parser
								if ($host->connectIp.':'.$host->port == $peerInfo)
									$this->handlePacket($data, $hostID);
							}
							else
							{
								// TCP Stream requires buffering
								$host->appendToBuffer($data);
								while (true) {
									//console('findloop');
									$packet = $host->findNextPacket();
									if (!$packet)
										break;
									
									// Handle the packet here
									$this->handlePacket($packet, $hostID);
								}
							}
						}
						
						if ($numReady == 0)
							break 2;
					}

					// Did the host send us something on our separate udp port (if we have that active to begin with)?
					if ($host->udpPort > 0 && in_array($host->socketMCI, $sockReads))
					{
						$numReady--;
						
						$peerInfo = '';
						$data = $host->readMCI($peerInfo);
						$exp = explode(':', $peerInfo);
						console('received '.strlen($data).' bytes on second socket');

						// Only process the packet if it came from the host's IP.
						if ($host->connectIp == $exp[0])
							$this->handlePacket($data, $hostID);
					}
				}
				unset($host);
				
				// KB input
				if (in_array (STDIN, $sockReads))
				{
					$numReady--;
					$kbInput = trim(fread (STDIN, STREAM_READ_BYTES));
					
					// Split up the input
					$exp = explode (' ', $kbInput);
	
					// Process the command (the first char or word of the line)
					switch ($exp[0])
					{
						case 'h':
							foreach ($this->hosts as $hostID => $host)
							{
								console($hostID.' => '.$host->ip.':'.$host->port.(($host->udpPort > 0) ? '+udp'.$host->udpPort : '').' -> '.(($host->connStatus == CONN_CONNECTED) ? '' : (($host->connStatus == CONN_VERIFIED) ? 'verified &' : 'not')).' connected');
							}
							break;
						
						case 'p':
							console("#\tName\tVersion\tAuthor\tDescription");
							foreach ($this->plugins as $pluginID => $plugin)
							{
								console($pluginID."\t#".$plugin::NAME."\t".$plugin::VERSION."\t".$plugin::AUTHOR."\t".$plugin::DESCRIPTION);
							}
							break;
						
						case 'x':
							$this->isRunning = FALSE;
							break;
						
						case 'w':
							for ($k=0; $k<$this->httpNumClients; $k++)
							{
								$lastAct = time() - $this->httpClients[$k]->lastActivity;
								console($this->httpClients[$k]->ip.':'.$this->httpClients[$k]->port.' - last activity was '.$lastAct.' second'.(($lastAct = 1) ? '' : 's').' ago.');
							}
							console('Counted '.$this->httpNumClients.' http client'.(($this->httpNumClients == 1) ? '' : 's'));
							break;
						
						default :
							console('Available Commands:');
							console('h - show host info');
							console('p - show plugin info');
							console('x - exit PHPInSimMod');
							console('w - show www connections');
					}
				}
				
			} // End while(numReady)
			
			// No need to do the maintenance check every turn
			if ($this->nextMaintenance > time ())
				continue;
			$this->nextMaintenance = time () + MAINTENANCE_INTERVAL;
	
			// InSim Connection maintenance
			foreach($this->hosts as $hostID => $host)
			{
				if ($host->connStatus == CONN_NOTCONNECTED)
					continue;
				else if ($host->connStatus == CONN_CONNECTING)
				{
					// Check to see if a connection attempt is going to time out.
					if ($host->connTime < time() - CONN_TIMEOUT)
					{
						console('Connection attempt to '.$host->ip.':'.$host->port.' timed out');
						$host->close();
					}
					continue;
				}
				
				// Does the connection appear to be dead? (LFS host not sending anything for more than HOST_TIMEOUT seconds
				if ($host->lastReadTime < time () - HOST_TIMEOUT)
				{
					console('Host '.$host->ip.':'.$host->port.' timed out');
					$host->close();
				}
				
				// Do we need to keep the connection alive with a ping?
				if ($host->lastWriteTime < time () - KEEPALIVE_TIME)
				{
					$ISP = new IS_TINY();
					$ISP->SubT = TINY_NONE;
					$host->writePacket($ISP);
				}
			}
			
			// unset the temporary var $host to prevent weirdness.
			unset($host);
						
		} // End while(isRunning)
	}

	private function handlePacket(&$rawPacket, &$hostID)
	{
		global $TYPEs;
		
		// Check packet size
		if ((strlen($rawPacket) % 4) > 0)
		{
			// Packet size is not a multiple of 4
			console('WARNING : packet with invalid size ('.strlen($rawPacket).') from '.$hostID);
			
			// Let's clear the buffer to be sure, because remaining data cannot be trusted at this point.
			$this->hosts[$hostID]->clearBuffer();
			
			// Do we want to do anything else at this point?
			// Count errors? Disconnect host?
			// My preference would go towards counting the amount of times this error occurs and hang up after perhaps 3 errors.
			
			return;
		}
		
		# Parse Packet Header
		$pH = unpack('CSize/CType/CReqI/CData', $rawPacket);
		if (isset($TYPEs[$pH['Type']]))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console($TYPEs[$pH['Type']] . ' Packet from '.$hostID);
			$packet = new $TYPEs[$pH['Type']]($rawPacket);
			$this->inspectPacket($packet, $hostID);
			$this->dispatchPacket($packet, $hostID);
		}
		else
		{
			console("Unknown Type Byte of ${pH['Type']}, with reported size of ${pH['Size']} Bytes and actual size of " . strlen($rawPacket) . ' Bytes.');
		}
	}
	
	// inspectPacket is used to act upon certain packets like error messages
	// We need these packets for proper basic PRISM connection functionality
	//
	private function inspectPacket(&$packet, &$hostID)
	{
		switch($packet->Type)
		{
			case ISP_VER :
				// When receiving ISP_VER we can conclude that we now have a working insim connection.
				if ($this->hosts[$hostID]->connStatus != CONN_VERIFIED)
				{
					// Because we can receive more than one ISP_VER, we only set this the first time
					$this->hosts[$hostID]->connStatus	= CONN_VERIFIED;
					$this->hosts[$hostID]->connTime		= time();
					$this->hosts[$hostID]->connTries	= 0;
					
					// Send out some info requests
					$ISP = new IS_TINY();
					$ISP->SubT = TINY_NCN;
					$ISP->ReqI = 1;
					$this->hosts[$hostID]->writePacket($ISP);
					$ISP = new IS_TINY();
					$ISP->SubT = TINY_NPL;
					$ISP->ReqI = 1;
					$this->hosts[$hostID]->writePacket($ISP);
					$ISP = new IS_TINY();
					$ISP->SubT = TINY_RES;
					$ISP->ReqI = 1;
					$this->hosts[$hostID]->writePacket($ISP);
				}
				break;

			case IRP_ERR :
				switch($packet->ErrNo)
				{
					case IR_ERR_PACKET :
						console('Invalid packet sent by client (wrong structure / length)');
						break;

					case IR_ERR_PACKET2 :
						console('Invalid packet sent by client (packet was not allowed to be forwarded to host)');
						break;

					case IR_ERR_HOSTNAME :
						console('Wrong hostname given by client');
						break;

					case IR_ERR_ADMIN :
						console('Wrong admin pass given by client');
						break;

					case IR_ERR_SPEC :
						console('Wrong spec pass given by client');
						break;

					case IR_ERR_NOSPEC :
						console('Spectator pass required, but none given');
						break;

					default :
						console('Unknown error received from relay ('.$packet->ErrNo.')');
						break;
				}
				break;
		}
	}
	
	private function isPluginEligibleForPacket(&$name, &$hostID)
	{
		foreach ($this->pluginvars[$name]['useHosts'] as $host)
		{
			if ($host == $hostID)
				return TRUE;
		}
		return FALSE;
	}
	
	private function dispatchPacket(&$packet, &$hostID)
	{
		$this->curHostID = $hostID;
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

	public function sendPacket($packetClass, $HostId = FALSE)
	{
		if ($HostId === FALSE)
			return $this->hosts[$this->curHostID]->writePacket($packetClass);
		else
			return $this->hosts[$HostId]->writePacket($packetClass);
	}

	private function getSocketTimeOut()
	{
		# If timer & cron array is empty, set the Sleep & uSleep to NULL.
		# Else set the timeout to the detla of now as compared to the next timer or cronjob event, what ever is smaller.
		# A Cron Jobs distance to now will have to be recalcuated after each socket_select call, well do that here also.

		// Must have a max delay of a second, otherwise there is no connection maintenance done.
		$this->sleep = 1;
		$this->uSleep = null;
	}

	public function __destruct()
	{
		console('Safe shutdown: ' . date($this->cvars['logFormat']));
	}
}

function console($line, $EOL = true)
{
	// Add log to file
	// Effected by PRISM_LOG_MODE && PRISM_LOG_FILE_MODE
	echo $line . (($EOL) ? PHP_EOL : '');
}

function get_dir_structure($path, $recursive = TRUE, $ext = NULL)
{
	$return = NULL;
	if (!is_dir($path))
	{
		trigger_error('$path is not a directory!', E_USER_WARNING);
		return FALSE;
	}
	if ($handle = opendir($path))
	{
		while (FALSE !== ($item = readdir($handle)))
		{
			if ($item != '.' && $item != '..')
			{
				if (is_dir($path . $item))
				{
					if ($recursive)
					{
						$return[$item] = get_dir_structure($path . $item . '/', $recursive, $ext);
					}
					else
					{
						$return[$item] = array();
					}
				}
				else
				{
					if ($ext != null && strrpos($item, $ext) !== FALSE)
					{
						$return[] = $item;
					}
				}
			}
		}
		closedir($handle);
	}
	return $return;
}

?>