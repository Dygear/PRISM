<?php
class LVS extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'LVS';
	const AUTHOR = 'PRISM Dev Team & avetere';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Lap Verification System.';

	private $pathPoly = array();
	private $onTrack = array();

	public function __construct()
	{
		$this->registerPacket('onTrack', ISP_STA);
		$this->registerPacket('onMCI', ISP_MCI);
	}

	public function onTrack(IS_STA $STA)
	{
		static $Track;
		if ($Track == $STA->Track)
			return PLUGIN_CONTINUE;
		else
			$Track = $STA->Track;

		$file = ROOTPATH . '/data/pth/' . $Track . '.pth';

		if (!file_exists($file))
			return PLUGIN_CONTINUE;

		$pth = new PTH($file);
		$this->pathPoly = array();
		foreach ($pth->Nodes as $NodeID => $Node)
			$this->pathPoly[$NodeID] = $Node->toPointRoad();
		
		print_r($this->pathPoly);
		
		console('Read track path file successfully.');
	}
	
	public function onMCI(IS_MCI $MCI)
	{
		foreach ($MCI->Info as $Info)
		{
			if ($this->inPoly($Info->X, $Info->Y, $this->pathPoly))
				$Text = '^4On Track^9';
			else
				$Text = '^1Off Track^9';

			IS_MTC()->PLID($Info->PLID)->Text("{$Text} - {$Info->Node}: {$Info->X}, {$Info->Y}")->Send();
		}
		exit();
	}
}
?>