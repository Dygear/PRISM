<?php
/**
 * PHPInSimMod - Plugin Module
 * @package PRISM
 * @subpackage Plugin
*/

define('CLIENT_PRINT_CHAT', 1);

abstract class Plugins
{
	/** These consts should ALWAYS be defined in your classes. */
	/* const NAME;			*/
	/* const DESCRIPTION;	*/
	/* const AUTHOR;		*/
	/* const VERSION;		*/

	/** Properties */
	// Timers
	public $crons = array();
	public $timers = array();
	public $callbacks = array();
	// Callbacks
	private $callbackConsole = array(); # registerConsoleCommand & registerCommand
	private $callbackInteractive = array(); # registerInteractiveCommand & registerCommand
	private $callbackOptions = array(); # registerOptionCommand & registerCommand
	private $callbackPackets = array(); # registerPacket
	private $callbackSay = array(); # registerSayCommand & registerCommand

	/** Construct */
	public function __construct(&$parent)
	{
		$this->parent =& $parent;
	}
	protected function sendPacket($packetClass)
	{
		return $this->parent->sendPacket($packetClass);
	}

	/** Handle Methods */
	public function handlePacket($packet) {}

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
	protected function registerCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1) {}
	// Any command that comes from the PRISM console. (STDIN)
	protected function registerConsoleCommand($cmd, $callbackMethod, $info = "") {}
	// Any command that comes from the "/i" type. (III)
	protected function registerInteractiveCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1) {}
	// Any command that comes from the "/o" type. (III)
	protected function registerOptionCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1) {}
	// Any say event with prefix charater (ISI->Prefix) with this command type. (MSO->Flags | MSO_PREFIX)
	protected function registerSayCommand($cmd, $callbackMethod, $info = "", $defaultAdminLevelToAccess = -1) {}

	// Setups a timer to run at a certain interval.
	protected function registerTimerInterval($interval, $callbackMethod)
	{
	
	}
	// Schedules a method call to run periodically at certain times or dates.
	protected function registerTimerCron($cronExpression, $callbackMethod)
	{	# The name cron comes from the word "chronos", Greek for "time".
		
	}

	// Sets up a Console Varable (CVAR) to be utlizied by this plugin.
	public function registerCvar($cvar, $defaultValue, $defaultAdminLevelToChange) {}

	/** Server Methods */
	public function serverPrint($Msg) {}
	public function serverSay($Msg) {}
	public function serverGetTrack() {}
	public function serverGetName() {}
	public function serverGetSectors() {}
	public function serverGetClients() {}
	public function serverGetPlayers() {}
	public function serverGetPacket() {}

	/** Client Methods */
	public function clientCanAccessCmd($CLID, $cmd) {}
	public function clientPrint($CLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}
	public function clientIsSpectator($CLID)
	{
		# Returns true when the client is connected.
		# AND all PLIDs spawned by this client are AIs.
	}

	/** Player Methods */
	public function playerIsAI($PLID) {}
	public function playerPrint($PLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}

}

?>