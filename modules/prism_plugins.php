<?php
/**
 * PHPInSimMod - Plugin Module
 * @package PRISM
 * @subpackage Plugin
*/

abstract class plugins extends PHPInSimMod
{
	/* const NAME;			*/
	/* const DESCRIPTION;	*/
	/* const AUTHOR;		*/
	/* const VERSION;		*/

	/* Construct */

	/* Plugin Functions */
	protected function registerPacket($callbackMethod, $PacketType)
	{
		$this->callbacks[$PacketType][] = $callbackMethod;
		$args = func_get_args();
		for ($i = 2, $j = count($args); $i < $j; ++$i)
			$this->callbacks[$args[$i]][] = $callbackMethod;
	}
	public function registerCvar($cvar, $defaultValue, $defaultAdminLevelToChange) {}
	public function registerCmd($cmd, $callback, $defaultAdminLevelToAccess) {}
	public function registerTimeOut($length, $callback) {}
	public function registerSay($say, $callback, $defaultAdminLevelToAccess) {}

	/* Server Functions */
	public function serverPrint($Msg) {}
	public function serverSay($Msg) {}
	public function serverGetTrack() {}
	public function serverGetName() {}
	public function serverGetSectors() {}
	public function serverGetClients() {}
	public function serverGetPlayers() {}
	public function serverGetPacket() {}

	/* Client Functions */
	public function clientCanAccessCmd($cmd, $CLID)
	{
		if ($this->cmds[$cmd]->AccessLevel | $this->clientGetAccessLevel($CLID))
			return TRUE;
		$this->console("Access denied to $cmd, for client {$this->clientGetUName($CLID)}.");
		$this->clientPrint($CLID, "Access Denied!"); # You Slime Bag!
		return FALSE;
	}

	/* Client Functions */
	public function clientPrint($CLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}
	public function clientCommand() {}
	public function clientAuthorized() {}

	public function clientGetUName($CLID) {}
	public function clientGetPNames() {}
	public function clientGetId() {}
	public function clientGetPlayerIds() {}
	public function clientGetAccessLevel($CLID) {}

	public function clientIsConnected() {}
	public function clientIsAI() {}
	public function clientIsObserver() {}
	public function clientIsSpectator() {}
	public function clientGetInfo() {}

	/* Player Functions */
	public function playerPrint($PLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}

}

?>