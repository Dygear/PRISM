<?php
class reorder extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Reorder Plugin';
	const AUTHOR = 'Dygear & misiek08';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Used as a shell for quickly writing down ideas and seeing how they work.';

	private $PLID = array();
	private $reorder = FALSE;
	private $IS_REO;

	public function __construct()
	{
		$this->IS_REO = new IS_REO;
		$this->registerSayCommand('prism reo', 'cmdREO', "<PName> ... - Set's the grid for the next race.", ADMIN_VOTE);
		$this->registerPacket('onReorder', ISP_REO);
		$this->registerPacket('onVoteAction', ISP_TINY);
	}
	public function cmdREO($Msg, $UCID)
	{
		if (($argc = count($argv = str_getcsv($cmd, ' '))) < 2)
		{
			$this->clientPrint($UCID, PRINT_CHAT, 'You must give at least one PName.');
			return PLUGIN_CONTINUE;
		}

		$this->reorder = TRUE;
		$this->PLID = array();

		foreach ($PNames as $PName)
			$this->PLID[] = $this->getPlayerByPName($PName)->PLID;

		$this->IS_REO->NumP(count($this->PLID))->PLID($this->PLID);

		return PLUGIN_HANDLED;
	}
	public function onReorder(IS_REO $REO)
	{
		$this->reorder = FALSE;	# As we are copying LFS REO state, we don't need to send this packet on TINY_VTA packet.

		foreach ($REO->PLID as $Pos => $PLID)
			console(sprintf("Pos: %02d | PLID: %02d | UName: %24s | PName: %24s", $Pos, $PLID, $this->getClientByPLID($PLID)->UName, $this->getPlayerByPLID($PLID)->PName));

		$this->IS_REO->NumP(count($REO->PLID))->PLID($REO->PLID); # Updates our list.

		return PLUGIN_CONTINUE;
	}
	public function onVoteAction(IS_TINY $TINY)
	{
		if ($this->reorder AND $TINY->SubT == SMALL_VTA AND $this->IS_REO instanceof IS_REO)
			$this->IS_REO->Send();
		return PLUGIN_CONTINUE;
	}
}
?>