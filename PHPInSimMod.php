<?php
/* PHPInSimMod
*
* by the PHPInSimMod Development Team.
*
*/

/* Defines */
// PRISM

define('PRISM_DEBUG_CORE',    	1);			# Shows Debug Messages From the Core
define('PRISM_DEBUG_SOCKETS',	2);			# Shows Debug Messages From the Sockets Module
define('PRISM_DEBUG_MODULES',	4);			# Shows Debug Messages From the all Modules
define('PRISM_DEBUG_PLUGINS',	8);			# Shows Debug Messages From the Plugins
define('PRISM_DEBUG_ALL',		15);		# Shows Debug Messages From All

define('MAINTENANCE_INTERVAL', 	2);			# The frequency in seconds to do connection maintenance checks.

// Return Codes:
define('PLUGIN_CONTINUE',		0);			# Plugin passes through operation. Whatever called it continues.
define('PLUGIN_HANDLED',		1);			# Plugin halts continued operation. Plugins following in the plugins.ini won't be called.
define('PLUGIN_STOP',			2);			# Plugin stops timer from triggering again in the future.

error_reporting(E_ALL);
ini_set('display_errors',		'true');

define('ROOTPATH', dirname(realpath(__FILE__)));

// the REQUIRED modules for PRISM.
require_once(ROOTPATH . '/modules/prism_functions.php');
require_once(ROOTPATH . '/modules/prism_config.php');
require_once(ROOTPATH . '/modules/prism_packets.php');
require_once(ROOTPATH . '/modules/prism_hosts.php');
require_once(ROOTPATH . '/modules/prism_statehandler.php');
require_once(ROOTPATH . '/modules/prism_http.php');
require_once(ROOTPATH . '/modules/prism_telnet.php');
require_once(ROOTPATH . '/modules/prism_admins.php');
require_once(ROOTPATH . '/modules/prism_timers.php');
require_once(ROOTPATH . '/modules/prism_plugins.php');


$PRISM = new PHPInSimMod();
$PRISM->initialise($argc, $argv);
$PRISM->start();

/**
 * PHPInSimMod
 * @package PRISM
 * @author Dygear (Mark Tomlin) <Dygear@gmail.com>
 * @author ripnet (Tom Young) <ripnet@gmail.com>
 * @author morpha (Constantin Köpplinger) <morpha@xigmo.net>
 * @author Victor (Victor van Vlaardingen) <vic@lfs.net>
 * @author GeForz (Kai Lochbaum)
*/
class PHPInSimMod
{
    const VERSION = '0.4.4';
    const ROOTPATH = ROOTPATH;

    /* Run Time Arrays */
    public $config				= null;
    public $hosts				= null;
    public $http				= null;
    public $telnet				= null;
    public $plugins				= null;
    public $admins				= null;

    # Time outs
    private $sleep				= null;
    private $uSleep				= null;

    private $nextMaintenance	= 0;
    public $isWindows			= false;

    // Main while loop will run as long as this is set to true.
    private $isRunning			= false;

    // Real Magic Functions
    public function __construct()
    {
        // This reregisters our autoload magic function into the class.
        spl_autoload_register(__CLASS__ . '::_autoload');
        set_error_handler(__CLASS__ . '::_errorHandler', E_ALL | E_STRICT);

        // Windows OS check
        if (preg_match('/^win/i', PHP_OS))
            $this->isWindows = true;

        $this->config	= new ConfigHandler();
        $this->hosts	= new HostHandler();
        $this->plugins	= new PluginHandler();
        $this->http		= new HttpHandler();
        $this->telnet	= new TelnetHandler();
        $this->admins	= new AdminHandler();
    }

    // Pseudo Magic Functions
    private static function _autoload($className)
    {
        require_once(ROOTPATH . "/modules/prism_" . strtolower($className) . ".php");
    }

    public static function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        # This error code is not included in error_reporting
        if (!(error_reporting() & $errno))
            return;

        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                    echo 'PHP ERROR:'.PHP_EOL;
                    $andExit = true;
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
        foreach ($trace as $index => $call) {
            if ($call['function'] == 'main') break;
            if ($index > 0 AND isset($call['file']) AND isset($call['line'])) {
                console("\t".$index.' :: '.$call['function'].' in '.$call['file'].':'.$call['line']);
            }
        }

        if (isset($andExit) AND $andExit == true)
            exit(1);

        # Don't execute PHP internal error handler

        return true;
    }

    public function initialise($argc, $argv)
    {
        // Set the timezone
        if (isset($this->config->cvars['defaultTimeZone']))
            date_default_timezone_set($this->config->cvars['defaultTimeZone']);
        else {
            # I know, I'm using error suppression, but I swear it's appropriate!
            $timeZoneGuess = @date_default_timezone_get();
            date_default_timezone_set($timeZoneGuess);
            unset($timeZoneGuess);
        }

        // Initialise handlers (load config files)
        if (!$this->config->initialise() OR
            !$this->hosts->initialise() OR
            !$this->http->initialise() OR
            !$this->telnet->initialise() OR
            !$this->admins->initialise() OR
            !$this->plugins->initialise())
        {
            console('Fatal error encountered. Exiting...');
            exit(1);
        }

        $pluginsLoaded = $this->plugins->loadPlugins();

        if ($this->config->cvars['debugMode'] & PRISM_DEBUG_CORE) {
            if ($pluginsLoaded == 0)
                console('No Plugins Loaded');
            else if ($pluginsLoaded == 1)
                console('One Plugin Loaded');
            else
                console("{$pluginsLoaded} Plugins Loaded.");
        }
    }

    public function start()
    {
        if ($this->isRunning)
            return;

        $this->isRunning = true;
        $this->nextMaintenance = time () + MAINTENANCE_INTERVAL;

        $this->main();
    }

    private function main()
    {
        while ($this->isRunning === true) {
            // Setup our listen arrays
            $sockReads = $sockWrites = $socketExcept = array();

            if (!$this->isWindows)
                $sockReads[] = STDIN;

            // Add host sockets to the arrays as needed
            // While at it, check if we need to connect to any of the hosts.
            $this->hosts->getSelectableSockets($sockReads, $sockWrites);

            // Add http sockets to the arrays as needed
            $this->http->getSelectableSockets($sockReads, $sockWrites);

            // Add telnet sockets to the arrays as needed
            $this->telnet->getSelectableSockets($sockReads, $sockWrites);

            // Update timeout if there are timers waiting to be fired.
            $this->updateSelectTimeOut($this->sleep, $this->uSleep);

            # Error suppression used because this function returns a "Invalid CRT parameters detected" only on Windows.
            $numReady = @stream_select($sockReads, $sockWrites, $socketExcept, $this->sleep, $this->uSleep);

            // Keep looping until you've handled all activities on the sockets.
            while ($numReady > 0) {
                $numReady -= $this->hosts->checkTraffic($sockReads, $sockWrites);
                $numReady -= $this->http->checkTraffic($sockReads, $sockWrites);
                $numReady -= $this->telnet->checkTraffic($sockReads, $sockWrites);

                // KB input
                if (in_array (STDIN, $sockReads)) {
                    $numReady--;
                    $kbInput = trim(fread (STDIN, STREAM_READ_BYTES));

                    // Split up the input
                    $exp = explode (' ', $kbInput);

                    // Process the command (the first char or word of the line)
                    switch ($exp[0]) {
                        case 'c':
                            console(sprintf('%32s - %64s', 'COMMAND', 'DESCRIPTOIN'));
                            foreach ($this->plugins->getPlugins() as $plugin => $details) {
                                foreach ($details->sayCommands as $command => $detail) {
                                    console(sprintf('%32s - %64s', $command, $detail['info']));
                                }
                            }

                            break;
                        case 'h':
                            console(sprintf('%14s %28s:%-5s %8s %22s', 'Host ID', 'IP', 'PORT', 'UDPPORT', 'STATUS'));
                            foreach ($this->hosts->getHostsInfo() as $host) {
                                $status = (($host['connStatus'] == CONN_CONNECTED) ? '' : (($host['connStatus'] == CONN_VERIFIED) ? 'VERIFIED &' : ' NOT')).' CONNECTED';
                                $socketType = (($host['socketType'] == SOCKTYPE_TCP) ? 'tcp://' : 'udp://');
                                console(sprintf('%14s %28s:%-5s %8s %22s', $host['id'], $socketType.$host['ip'], $host['port'], $host['udpPort'], $status));
                            }
                            break;

                        case 'I':
                            console('RE-INITIALISING PRISM...');
                            $this->initialise(null, null);
                            break;

                        case 'p':
                            console(sprintf('%28s %8s %24s %64s', 'NAME', 'VERSION', 'AUTHOR', 'DESCRIPTION'));
                            foreach ($this->plugins->getPlugins() as $plugin => $details) {
                                console(sprintf("%28s %8s %24s %64s", $plugin::NAME, $plugin::VERSION, $plugin::AUTHOR, $plugin::DESCRIPTION));
                            }
                            break;

                        case 'x':
                            $this->isRunning = false;
                            break;

                        case 'w':
                            console(sprintf('%15s:%5s %5s', 'IP', 'PORT', 'LAST ACTIVITY'));
                            foreach ($this->http->getHttpInfo() as $v) {
                                $lastAct = time() - $v['lastActivity'];
                                console(sprintf('%15s:%5s %13d', $v['ip'], $v['port'], $lastAct));
                            }
                            console('Counted '.$this->http->getHttpNumClients().' http client'.(($this->http->getHttpNumClients() == 1) ? '' : 's'));
                            break;

                        default :
                            console('Available Commands:');
                            console('	h - show host info');
                            console('	I - re-initialise PRISM (reload ini files / reconnect to hosts / reset http socket');
                            console('	p - show plugin info');
                            console('	x - exit PHPInSimMod');
                            console('	w - show www connections');
                            console('	c - show command list');
                    }
                }

            } // End while(numReady)

            // No need to do the maintenance check every turn
            if ($this->nextMaintenance > time ())
                continue;
            $this->nextMaintenance = time () + MAINTENANCE_INTERVAL;
            if (!$this->hosts->maintenance())
                $this->isRunning = false;
            $this->http->maintenance();
            PHPParser::cleanSessions();

        } // End while(isRunning)
    }

    private function updateSelectTimeOut(&$sleep, &$uSleep)
    {
        $sleep = 1;
        $uSleep = null;

        $sleepTime = null;
        foreach ($this->plugins->getPlugins() as $plugin => $object) {
            $timeout = $object->executeTimers();

            if ($timeout < $sleepTime)
                $sleepTime = $timeout;
        }

        # If there are no timers set or the next timeout is more then a second away, set the Sleep to 1 & uSleep to null.
        if ($sleepTime == null || $timeout < $sleepTime) {
            $sleepTime = $timeout;
        } else {	# Set the timeout to the delta of now as compared to the next timer.
            list($sleep, $uSleep) = explode('.', sprintf('%1.6f', $timeNow - $sleepTime));
            if (($sleep >= 1 && $uSleep >= 1) || $uSleep >= 1000000) {
                $sleep = 1;
                $uSleep = null;
            }
        }
    }

    public function __destruct()
    {
        console('Safe shutdown: ' . date($this->config->cvars['logFormat']));
    }
}
