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

define('MAINT_INERVAL', 		3);			# The frequency in seconds to do connection maintenance checks. 3 should be totally fine.

// Admin
define('ADMIN_ALL',				0);			# Everyone
define('ADMIN_IMMUNITY',		1);			# Flag "a", immunity
define('ADMIN_RESERVATION',		2);			# Flag "b", reservation
define('ADMIN_KICK',			4);			# Flag "c", kick
define('ADMIN_BAN',				8);			# Flag "d", ban
define('ADMIN_SLAY',			16);		# Flag "e", slay
define('ADMIN_MAP',				32);		# Flag "f", map change
define('ADMIN_CVAR',			64);		# Flag "g", cvar change
define('ADMIN_CFG',				128);		# Flag "h", config execution
define('ADMIN_CHAT',			256);		# Flag "i", chat
define('ADMIN_VOTE',			512);		# Flag "j", vote
define('ADMIN_PASSWORD',		1024);		# Flag "k", sv_password
define('ADMIN_RCON',			2048);		# Flag "l", rcon access
define('ADMIN_LEVEL_A',			4096);		# Flag "m", custom
define('ADMIN_LEVEL_B',			8192);		# Flag "n", custom
define('ADMIN_LEVEL_C',			16384);		# Flag "o", custom
define('ADMIN_LEVEL_D',			32768);		# Flag "p", custom
define('ADMIN_LEVEL_E',			65536);		# Flag "q", custom
define('ADMIN_LEVEL_F',			131072);	# Flag "r", custom
define('ADMIN_LEVEL_G',			262144);	# Flag "s", custom
define('ADMIN_LEVEL_H',			524288);	# Flag "t", custom
define('ADMIN_MENU',			1048576);	# Flag "u", menus
define('ADMIN_ADMIN',			16777216);	# Flag "y", default admin
define('ADMIN_USER',			33554432);	# Flag "z", default user

// Return Codes: 
define('PLUGIN_CONTINUE',		0);			# Plugin passes through operation. Whatever called it continues.
define('PLUGIN_HANDLED',		1);			# Plugin halts continued operation. Plugins following in the plugins.ini won't be called.

error_reporting(E_ALL);
ini_set ('display_errors', 'true');

define('ROOTPATH', dirname(realpath(__FILE__)));

// the packets and connections module are two of the three REQUIRED modules for PRISM.
require_once(ROOTPATH . '/modules/prism_packets.php');
require_once(ROOTPATH . '/modules/prism_connections.php');

$PRISM = new PHPInSimMod($argc, $argv);

/**
 * PHPInSimMod
 * @package PRISM
 * @coauthor Dygear (Mark Tomlin) <Dygear@gmail.com>
 * @coauthor ripnet (Tom Young) <ripnet@gmail.com>
 * @coauthor morpha (Constantin Köpplinger) <morpha@xigmo.net>
*/
class PHPInSimMod
{
	const VERSION = '0.1.6';
	const ROOTPATH = ROOTPATH;

	/* Run Time Arrays */
	// Resources
	private $sql;
	// Basicly Read Only
	private $cvars			= array();
	private $connvars		= array();
	private $pluginvars		= array();

	private $hosts			= array();			// Stores references to the hosts we're connected to
	private $nextMaint		= 0;

	// InSim Changed Arrays
//	private $clients = array();
//	private $players = array();

	# Time outs
	private $sleep = 0;
	private $uSleep = 1000;

	// Main while loop will run as long as this is set to TRUE.
	private $isRunning = TRUE;

	private function loadIniFiles()
	{
		// Load generic cvars.ini
		if ($this->loadIniFile($this->cvars, 'cvars.ini', FALSE))
		{
			if (!isset($this->cvars['debugMode']))
				$this->cvars['debugMode'] = '';
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded cvars.ini');
		}
		else
		{
			return FALSE;
		}

		// Load connections.ini
		if ($this->loadIniFile($this->connvars, 'connections.ini'))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded connections.ini');
		}
		else
		{
			return FALSE;
		}

		// Load plugins.ini
		if ($this->loadIniFile($this->pluginvars, 'plugins.ini'))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				console('Loaded plugins.ini');
		}
		else
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	// Generic function to load ini files into a passed variable
	private function loadIniFile(&$target, $iniFile, $parseSections = TRUE)
	{
		// Should parse the $PismDir/config/cvars.ini file, and load them into the $this->cvars array.
		$iniPath = $this::ROOTPATH . '/configs/'.$iniFile;
		
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
		$target = $iniVARs;

		# At this point we're always successful
		return true;
	}

	// Pseudo Magic Functions
	private static function _autoload($className)
	{
		require_once(ROOTPATH . "/modules/prism_{$className}.php");
	}

	// Real Magic Functions
	public function __construct($argc, $argv)
	{
		// This reregisters our autoload magic function into the class.
		spl_autoload_register(__CLASS__ . '::_autoload');

		// Load ini files
		if (!$this->loadIniFiles())
		{
			console('Fatal error encountered. Exiting...');
			return;
		}
		
		// Populate $this->hosts array from the connections.ini variables we've just read
		$this->populateHostsFromVars();
		
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

		$this->nextMaint = time () + MAINT_INERVAL;
		$this->main();
	}

	private function populateHostsFromVars()
	{
		foreach ($this->connvars as $hostID => $v)
		{
			if (isset($v['useRelay']))
			{
				// This is a Relay connection
				$hostName		= isset($v['hostname']) ? substr($v['hostname'], 0, 31) : '';
				$adminPass		= isset($v['adminPass']) ? substr($v['adminPass'], 0, 15) : '';
				$specPass		= isset($v['specPass']) ? substr($v['specPass'], 0, 15) : '';
				if ($hostName == '')
					continue;
				
				$ic				= new insimConnection(CONNTYPE_RELAY, SOCKTYPE_TCP);
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
				$pps			= isset($v['pps']) ? (int) $v['pps'] : 3;
				$adminPass		= isset($v['password']) ? $v['password'] : '';
				$socketType		= isset($v['socketType']) ? $v['socketType'] : SOCKTYPE_TCP;
				
				$ic				= new insimConnection(CONNTYPE_HOST, $socketType);
				$ic->id			= $hostID;
				$ic->ip			= $ip;
				$ic->port		= $port;
				$ic->pps		= $pps;
				$ic->adminPass	= $adminPass;

				$this->hosts[$hostID] = $ic;
			}
		}
	}
	
	private function main()
	{
		while ($this->isRunning === TRUE)
		{
			$this->getSocketTimeOut();
	
			// Setup our listen arrays
			$sockReads = array(STDIN);
			$sockWrites = array();
			
			// Add host sockets to the arrays as needed
			// While at it, check if we need to connect to any of the hosts.
			foreach ($this->hosts as $hostID => $host)
			{
				if ($host->connStatus == CONN_CONNECTED)
				{
					if (is_resource($host->socket))
					{
						$sockReads[] = $host->socket;
						
						// Is the host is lagged, we must check to see when we can write again
						if ($host->sendQStatus > 0)
							$sockWrites[] = $host->socket;
					}
				}
				else if ($host->connStatus == CONN_CONNECTING)
				{
					$sockWrites[] = $host->socket;
				}
				else if ($host->connStatus == CONN_NOTCONNECTED)
				{
					// Should we try to connect?
					if ($host->mustConnect > -1 && $host->mustConnect < time())
						$host->connect();
				}
			}
			unset($host);
	
			// select
			$numReady = stream_select($sockReads, $sockWrites, $socketExcept = null, $this->sleep, $this->uSleep);
			
			// Keep looping until you've handled all activities on the sockets.
			while($numReady > 0)
			{
				// Host traffic
				foreach($this->hosts as $hostID => $host)
				{
					// Finalise a connection?
					if ($host->connStatus == CONN_CONNECTING && 
				    	in_array($host->socket, $sockWrites))
				    {
		       			$numReady--;
		       			
		       			// The socket has become available for writing
		       			$host->connectFinish();
			    	}

					// Recover a lagged host?
					if ($host->connStatus == CONN_CONNECTED && 
						$host->sendQStatus > 0 &&
				    	in_array($host->socket, $sockWrites))
				    {
		       			$numReady--;
		       			
		       			// Flush the sendQ and handle possible overload again
						for ($a=0; $a<$host->sendQStatus; $a++)
						{
							$bytes = $host->write($host->sendQ[$a], TRUE);
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
		       			$data = $host->read();
		       			
		       			if (!$data)
		       			{
		       				$host->close();
		       			}
		       			else
		       			{
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
		       			
		   	    		if ($numReady == 0)
		       			    break 2;
		       		}
				}
				unset($host);
				
				// KB input
				if (in_array (STDIN, $sockReads)) {
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
    							console($hostID.' => '.$host->ip.':'.$host->port.' -> '.(($host->connStatus == CONN_CONNECTED) ? '' : 'not').' connected');
    						}
    						break;
    					
    					case 'x':
    						$this->isRunning = FALSE;
    						break;
    					
    					default :
    						console('Available keys :');
    						console('h - show host info');
    						console('x - exit PHPInSimMod');
    				}
				}

				if ($numReady == 0)
				    break;
			
			} // End while(numReady)
			
			// No need to do the maintenance check every turn
			if ($this->nextMaint > time ())
				continue;
			$this->nextMaint = time () + MAINT_INERVAL;
	
			// Connection maintenance
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
		global $TYPEs, $ISP, $IRP;
		
		# Parse Packet Header
		$pH = unpack('CSize/CType/CReqI/CData', $rawPacket);
		if (isset($ISP[$pH['Type']]) || isset($IRP[$pH['Type']]))
		{
			console('FROM '.$hostID);
			$this->dispatchPacket(new $TYPEs[$pH['Type']]($rawPacket), $hostID);
		}
		else
		{
			console("Unknown Type Byte of ${pH['Type']}, with reported size of ${pH['Size']} Bytes and actual size of " . strlen($rawPacket) . ' Bytes.');
		}
	}
	
	private function dispatchPacket($packetObject, &$hostID)
	{
		if (!isset($this->packetDispatch[$packetObject->Type]))
		{	# Optimization, if the packet we are looking for has no listeners don't go though the loop.
			return PLUGIN_HANDLED;
		}

		foreach ($this->packetDispatch[$packetObject->Type] as $listener)
		{
			echo $listener;
		}
	}

	private function getSocketTimeOut()
	{
		# If timer array is empty, set the Sleep & uSleep to NULL.
		# Else set the timer to when the next timer is going to go off.
		
		$this->sleep = 0;		// default select wait of 1000 microsecond (1 millisecond).
		$this->uSleep = 1000;
	}

	private function isSafeToInclude($filePath)
	{
		if (!file_exists($filePath))
			return FALSE;

		system('php -l ' . escapeshellcmd($filePath), $status);
		if ($status)
			return FALSE;
		else
			return TRUE;
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

?>