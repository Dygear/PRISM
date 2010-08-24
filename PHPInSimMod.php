<?php
/* PHPInSimMod
*
* by the PHPInSimMod Development Team.
*
*/

/* Defines */
// PRISM
define('PRISM_DEBUG_CORE',		1);			# Shows Debug Messages From the Core
define('PRISM_BEBUG_SOCKETS',	2);			# Shows Debug Messages From the Sockets Module
define('PRISM_DEBUG_MODULES',	4);			# Shows Debug Messages From the all Modules
define('PRISM_DEBUG_PLUGINS',	8);			# Shows Debug Messages From the Plugins
define('PRISM_DEBUG_ALL',		15);		# Shows Debug Messages From All

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

define('ROOTPATH', dirname(realpath(__FILE__)));

// the packets module is the one of the two REQUIRED modules for PRISM.
require_once(ROOTPATH . '/modules/prism_packets.php');

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
	const VERSION = '0.1.5';
	const ROOTPATH = ROOTPATH;

	/* Run Time Arrays */
	// Resources
	private $sql;
	// Basicly Read Only
	private $cvars = array();
	// InSim Changed Arrays
	private $hosts = array();
	private $clients = array();
	private $players = array();

	/* Sockets */
	# For sending and reciving packets.
	private $socket = NULL;
	// For the socket_select timeout (in the 'main' function).
	# The 'arrays'
	private	$socketRead = NULL;
	private $socketWrite = NULL;
	private $socketExcept = NULL;
	# Time outs
	private $sleep = NULL;
	private $uSleep = NULL;

	// Main while loop will run as long as this is set to TRUE.
	private $isRunning = TRUE;

	public function console($line)
	{
		// Add log to file
		// Effected by PRISM_LOG_MODE && PRISM_LOG_FILE_MODE
		echo $line . PHP_EOL;
	}

	private function loadDefaultCVars()
	{
		// Should parse the $PismDir/config/cvars.ini file, and load them into the $this->cvars array.
		$iniPath = $this::ROOTPATH . '/configs/cvars.ini';

		if (!file_exists($iniPath))
		{
			$this->console('Could not find CVAR File!');
			return FALSE;
		}
		if (($CVARs = parse_ini_file($iniPath)) === FALSE)
		{
			$this->console('Could not parse CVAR File!');
			return FALSE;
		}
		$this->cvars = $CVARs;

		# Basicly so long as $CVARs is not FALSE return TRUE
		return (!($CVARs === FALSE));
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

		if ($this->loadDefaultCVars())
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				$this->console('Loaded Default CVARs');
		}
		else
		{
			$this->console('Setting Emergency CVARs!');
			$this->cvars['debugMode'] = PRISM_DEBUG_ALL;
			$this->cvars['logFormat'] = 'r';
			$this->cvars['relayIP'] = 'isrelay.lfs.net';
			$this->cvars['relayPort'] = 47474;
		}

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

		// Remember, the filename is always the first arg, argv[0].
		// Get Command Line Arguments
		if ($argc > 1 && $argc <= 4)
		{
			// Does arg 1 contain a ':', donoting it might have a port number.
			# We only want the number past the last colon, as IPV6 may have many colons.
			$colonPos = strrpos($argv[1], ':');
			if ($colonPos !== false)
			{
				// It might have a port number, lets validate it
				$port = substr($argv[1], $colonPos + 1);

				if ( is_int($port) && ($port > 0) && ($port <= 65535) )
				{
					$this->cvars['port'] = $port;
				}
				else
				{
					if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
						$this->console('Invalid Port Number!');
				}
				$ipOrHostname = substr($argv[1], 0, $colonPos);
			}
			else
			{
				$ipOrHostname = $argv[1];
			}

			// Do we have an IP address?
			if (filter_var($ipOrHostname, FILTER_VALIDATE_IP))
			{ # Yep its a valid IP address
				$this->cvars['ip'] = $ipOrHostname;
			}
			/**
			* Dygear to Ripnet: Yeah I have no idea how this one works.
			*/
			elseif (preg_match("/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/", $ipOrHostname))
			{ # Yep its a valid hostname
				$this->cvars['hostname'] = $ipOrHostname;
			}
			else
			{ # Nope
				if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
					$this->console('Invalid Hostname');
			}
			// Cleanup
			unset($ipOrHostname, $colonPos, $port);

			if ($argc >= 3)
			{
				$this->cvars['password'] = $argv[2];
			}
			else
			{
				if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
					$this->console('WARNING: Password was left blank!');
			}
			
			// Read the optional argument on whether to use the Relay or Not.
			if ($argc == 4)
			{
				$this->cvars['useRelay'] = filter_var($argv[3], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			}
		}
		else if ($argc > 4)
		{ // Too many arguments!
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				$this->console('There should only be two arguments passed, got more then two!');
			$interactiveStartUp = TRUE;
		}
		else
		{ // No args
			$this->console('Usage: php PHPInSimMod.php <Hostname/IP[:port]> <Spec/Admin Password> [UseRelay]');
			$interactiveStartUp = TRUE;
		}

		if (array_key_exists('useRelay', $this->cvars) && $this->cvars['useRelay'] == TRUE)
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				$this->console("Connecting to {$this->cvars['hostname']} via relay, using password '{$this->cvars['password']}'.");
			$this->makeRelayConnection($this->cvars['hostname'], $this->cvars['password']);
			$interactiveStartUp = FALSE;
		}
		else if (array_key_exists('ip', $this->cvars))
		{
			if ($this->cvars['debugMode'] & PRISM_DEBUG_CORE)
				$this->console("Connecting to {$this->cvars['ip']}:{$this->cvars['port']}, using password '{$this->cvars['password']}'.");
			$this->makeDirectConnection($this->cvars['ip'], $this->cvars['port'], $this->cvars['password']);
			$interactiveStartUp = FALSE;
		}
		else
		{
			// Not really sure what the user wants.
			// None of the CVARs are set & no args passed.
			$interactiveStartUp = TRUE;
		}

		if (isset($interactiveStartUp) && $interactiveStartUp === TRUE)
		{
			$this->interactiveStartUp();
		}
	}

	public function interactiveStartUp()
	{
		$this->console('Starting interactive startup.');
		// http://www.php.net/manual/en/ref.readline.php
		# http://www.php.net/manual/en/function.stream-select.php
		// http://www.php.net/manual/en/install.windows.commandline.php
		// http://www.php.net/manual/en/function.ignore-user-abort.php
		// http://www.php.net/manual/en/features.commandline.io-streams.php
		// http://www.php.net/manual/en/features.gc.php
		// TODO
	}

	private function makeRelayConnection($hostname, $password)
	{
		$this->console('Attempting a Relay Connection.');
		# Attempt connection to relay
		// Relay connections are TCP by requirement.
		$this->socket = new socketTCP($this->cvars['relayIP'], $this->cvars['relayPort']);

		# If the socket is not connected, don't send any packets.
		if ($this->socket->isConnected() === FALSE)
			return;

		# Then attempt to find Hostname
		$SEL = new IR_SEL();
			$SEL->HName = $hostname;
			$SEL->Admin = $password;
		$this->socket->sendPacket($SEL);
		# Then ask relay for packets from host.
		$this->main();
	}

	private function makeDirectConnection($ip, $port, $password)
	{
		$this->console('Attempting a Direct Connection.');
		// Attempt connection to server.
		$this->socket = ($this->cvars['socketType'] ? new socketTCP($ip, $port) : new socketUDP($ip, $port, '127.0.0.1', $udpport));

		# If the socket is not connected, don't send any packets.
		if ($this->socket->isConnected() === FALSE)
			return;

		$ISP = new IS_ISI();
			$ISP->ReqI = TRUE;
			$ISP->UDPPort = 0;
			$ISP->Flags = 0;
			$ISP->Prefix = '!';
			$ISP->Interval = 0;
			$ISP->Admin = $password;
			$ISP->IName = 'PRISM v' . $this::VERSION;
		$this->socket->sendPacket($ISP);
		$this->main();
	}

	private function main()
	{
		while ($this->isRunning === TRUE)
		{
			$rawPacket = $this->packetWait();
			if ($rawPacket !== FALSE)
			{
				$this->handlePacket($rawPacket);
			}
		}
	}

	private function handlePacket($rawPacket)
	{
		global $TYPEs, $ISP, $IRP;
		# Parse Packet Header
		$pH = unpack('CSize/CType/CReqI/CData', $rawPacket);
		if (isset($ISP[$pH['Type']]) || isset($IRP[$pH['Type']]))
		{
			$this->dispatchPacket(new $TYPEs[$pH['Type']]($rawPacket));
		}
		else
		{
			$this->console("Unknown Type Byte of ${pH['Type']}, with reported size of ${pH['Size']} Bytes and actual size of " . strlen($rawPacket) . ' Bytes.');
		}
	}
	
	private function dispatchPacket($packetObject)
	{
		if ($packetObject->Type == ISP_TINY && $packetObject->SubT == TINY_NONE)
		{	# Send a Quick Reply
			$this->socket->sendPacket(new IS_TINY());
		}

		if (!isset($this->packetDispatch[$packetObject->Type]))
		{	# Optimization, if the packet we are looking for has no listeners don't go though the loop.
			return PLUGIN_HANDLED;
		}

		foreach ($this->packetDispatch[$packetObject->Type] as $listener)
		{
			echo $listener;
		}
	}

	private function packetWait()
	{
		$this->getSocketTimeOut();

		$this->socketRead = array($this->socket->sock());

		$skStatus = socket_select($this->socketRead, $this->socketWrite, $this->socketExcept, $this->sleep, $this->uSleep);

		if ($skStatus === FALSE)
		{
			$this->console('socket_select() failed, reason: ' . socket_strerror(socket_last_error()));
		}
		else if ($skStatus > 0)
		{
			/* At least at one of the sockets something interesting happened. */
			if ($rawPacket = $this->socket->recv())
			{
				return $rawPacket;
			}
		}
		else if ($skStatus === 0)
		{
			/* We triped over the timeout, in this case should check our timers
			   array to make sure that we don't need to fire one of those off
			   and we also need to send a keep alive packet to make sure we
			   don't lose our connection to the InSim service.
			*/
			# Keep Alive
			$ISP = new IS_TINY();
				$ISP->SubT = TINY_NONE;
			$this->socket->sendPacket($ISP);
		}
		else
		{
			$this->isRunning = FALSE;
			$this->console('I have no idea what just happend!');
		}

		return FALSE;
	}

	private function getSocketTimeOut()
	{
		# If timer array is empty, set the Sleep & uSleep to NULL.
		# Else set the timer to when the next timer is going to go off.
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
		$this->Console('Safe shutdown: ' . date($this->cvars['logFormat']));
	}

}

?>