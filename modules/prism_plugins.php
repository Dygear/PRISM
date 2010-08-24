<?php
/**
 * PHPInSimMod - Plugin Module
 * @package PRISM
 * @subpackage Plugin
 */

abstract class plugins
{
	/* const NAME;			*/
	/* const DESCRIPTION;	*/
	/* const AUTHOR;		*/
	/* const VERSION;		*/

	/* Shortcuts to the Parent */
	public function console($line)
	{
		$this->parent->console($line);
	}

	/* Plugin Functions */
	public function pluginRegisterCvar($cvar, $defaultValue, $defaultAdminLevelToChange) {}
	public function pluginRegisterCmd($cmd, $callback, $defaultAdminLevelToAccess) {}
	public function pluginRegisterTimeOut($length, $callback) {}
	public function pluginHookSay($say, $callback, $defaultAdminLevelToAccess) {}

	public function clientCanAccessCmd($cmd, $CLID)
	{
		if ($this->cmds[$cmd]->AccessLevel | $this->clientGetAccessLevel($CLID))
			return TRUE;
		$this->console("Access denied to $cmd, for client {$this->clientGetUName($CLID)}.");
		$this->clientPrint($CLID, "Access Denied!"); # You Slime Bag!
		return FALSE;
	}

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
	public function clientGetUName($CLID)
	{
		if (($UName = $this->parent->clients[$CLID]['UName']))
		{
			return $UName;
		}
		else
		{
			return FALSE;
		}
	}
	public function clientGetAccessLevel($CLID) {}
	public function clientPrint($CLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}

	public function clientConnect() {}
	public function clientConnected() {}
	public function clientDisconnect() {}
	public function clientCommand() {}
	public function clientSettingsChanged() {}
	public function clientAuthorized() {}

	public function clientGetUName() {}
	public function clientGetPNames() {}
	public function clientGetId() {}
	public function clientGetPlayerIds() {}

	public function clientIsConnected() {}
	public function clientIsAI() {}
	public function clientIsObserver() {}
	public function clientIsSpectator() {}
	public function clientGetInfo() {}

	/* Player Functions */
	public function playerPrint($PLID, $Msg, $Where = CLIENT_PRINT_CHAT) {}

}

?>