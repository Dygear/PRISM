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
ini_set('display_errors',		'true');

define('ROOTPATH', dirname(realpath(__FILE__)));

// the packets and connections module are two of the three REQUIRED modules for PRISM.
require_once(ROOTPATH . '/modules/prism_packets.php');
require_once(ROOTPATH . '/modules/prism_connections.php');

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
	const VERSION = '0.1.8';
	const ROOTPATH = ROOTPATH;

	private $isWindows		= FALSE;

	/* Run Time Arrays */
	// Resources
	private $sql;
	// Basicly Read Only
	private $cvars			= array();
	private $connvars		= array();
	private $pluginvars		= array();

	private $hosts			= array();			# Stores references to the hosts we're connected to
	private $nextMaintenance= 0;

	// InSim Changed Arrays
	private $clients		= array();
	private $players		= array();

	# Time outs
	private $sleep			= NULL;
	private $uSleep			= NULL;

	// Main while loop will run as long as this is set to TRUE.
	private $isRunning = TRUE;

	private function loadIniFiles()
	{
		// Load generic cvars.ini
		if ($this->loadIniFile($this->cvars, 'cvars.ini', FALSE))
		{
			if (!isset($this->cvars['debugMode']))
				$this->cvars['debugMode'] = '';
				
			// ADD MORE MISSING CONFIG VALUS HERE AS EMERGENCY DEFAULTS?
			
			
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
			return FALSE;
		}

		// Load plugins.ini
		if ($this->loadIniFile($this->pluginvars, 'plugins.ini'))
		{
			foreach ($this->pluginvars as $plguinID => $v)
			{
				if (!is_array($v))
				{
					console('Section error in plugins.ini file!');
					return FALSE;
				}
			}
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
	// If a ini file with the name and a prefix local_ already exists it is loaded instead
	private function loadIniFile(&$target, $iniFile, $parseSections = TRUE)
	{
		$iniVARs = FALSE;
		
		// Should parse the $PismDir/config/***.ini file, and load them into the $this->cvars array.
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
		$target = $iniVARs;

		# At this point we're always successful
		return TRUE;
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
			if ($index > 0)
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
					console('Invalid pps '.$ps.' for '.$hostID);
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
						console('Duplicate udpPort value found! Every host must have its own unique udpPort.');
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
						
						// Is the host is lagged, we must check to see when we can write again
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
						$host->connect();
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
					// Finalise a connection?
					if ($host->connStatus == CONN_CONNECTING && 
						in_array($host->socket, $sockWrites))
					{
						$numReady--;
						
						// The socket has become available for writing (or not)
						$host->connectFinish();
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
								console($hostID.' => '.$host->ip.':'.$host->port.(($host->udpPort > 0) ? '+udp'.$host->udpPort : '').' -> '.(($host->connStatus == CONN_CONNECTED) ? '' : (($host->connStatus == CONN_VERIFIED) ? 'verified &' : 'not')).' connected');
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
			if ($this->nextMaintenance > time ())
				continue;
			$this->nextMaintenance = time () + MAINTENANCE_INTERVAL;
	
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
		if (isset($ISP[$pH['Type']]) || isset($IRP[$pH['Type']]))
		{
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
	
	private function dispatchPacket(&$packet, &$hostID)
	{
		if (!isset($this->packetDispatch[$packet->Type]))
		{	# Optimization, if the packet we are looking for has no listeners don't go though the loop.
			return PLUGIN_HANDLED;
		}

		foreach ($this->packetDispatch[$packet->Type] as $listener)
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