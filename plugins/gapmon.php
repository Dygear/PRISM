<?php
class gapmon extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Gap Monitor';
	const AUTHOR = 'NotAnIllusion';
	const VERSION = '0.1.0';
	const DESCRIPTION = 'Gap Monitoring Tool';

	private $NumNodes;	# Total number of nodes in the path;
	private $Finish;	# Node index for the finish line;

	private $Players = array();
	private $NumPlayers = 0;

	public function __construct()
	{
		$this->registerPacket('onUpdate', ISP_NLP, ISP_MCI);
		$this->registerPacket('onRaceSTart', ISP_RST);
		$this->registerPacket('onNewPLayer', ISP_NPL);
		$this->registerPacket('onPLayerLeave', ISP_PLL);
	}

	public function onUpdate(Struct $Packet)
	{
		# If there is less then 2 players, this is not going to work, so abort the function.
		if ($this->NumPlayers < 2)
			return PLUGIN_CONTINUE;

		foreach ($Packet->Info as $Info)
		{
			$this->Players[$Info->PLID]->Node = ($Info->Node + $this->NumNodes - $this->Finish) % $this->NumNodes;
			$this->Players[$Info->PLID]->Lap = $Info->Lap;
			$this->Players[$Info->PLID]->Position = $Info->Position;
		}
		
		usort($this->Players, function ($a, $b) {
			return $a->Position - $b->Position;
		});
	}

	public function onRaceSTart(IS_RST $RST)
	{
		$this->NumNodes = $RST->NumNodes;
		$this->Finish = $RST->Finish;
	}

	public function onNewPLayer(IS_NPL $NPL)
	{
		$this->NumPlayers++;
		$this->Players[$NPL->PLID] = $NPL;
	}
	
	public function onPLayerLeave(IS_PLL $PLL)
	{
		$this->NumPlayers--;
		unset($this->Players[$PLL->PLID]);
	}
}
?>