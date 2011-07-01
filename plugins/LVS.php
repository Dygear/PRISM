<?php
class LVS extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'LVS';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Lap Verification System.';

	private $LVS = FALSE;
	private $Path = array();
	private $Track = '';

	public function __construct()
	{
		$this->registerSayCommand('prism lvs debug', 'cmdDebug', 'Prints Debug Information', ADMIN_ALL);
		$this->registerSayCommand('prism lvs', 'cmdLVSToggle', '<On|Off> - Turns Lap Verification System On / Off', ADMIN_CVAR + ADMIN_TRACK);
		$this->registerPacket('onTrack', ISP_STA, ISP_RST);
		$this->registerPacket('onMCI', ISP_MCI);
	}

	public function cmdDebug($cmd, $ucid)
	{
		$MTC = IS_MTC()->UCID($ucid);

		$debug = explode(PHP_EOL, print_r($this, TRUE));
		foreach ($debug as $line)
			$MTC->Text($line)->Send();
	}

	public function cmdLVSToggle($cmd, $ucid)
	{
		$MTC = IS_MTC()->UCID($ucid);
		if (($argc = count($argv = str_getcsv($cmd, ' '))) > 2)
		{
			$OnOff = strtolower($argv[2]);
			if ($OnOff == 'on')
			{
				$this->LVS = TRUE;
				$MTC->Text('Lap Verification System is now ^3On^8!');
			}
			else if ($OnOff == 'off')
			{
				$this->LVS = FALSE;
				$MTC->Text('Lap Verification System is now ^3Off^8!');
			}
			else
				$MTC->Text('Please provide a ^3On^8 or ^3Off^8 only as an argument to this command.');
		}
		else
			$MTC->Text('Lap Verification System is currently ^3' . (($this->LVS) ? 'On' : 'Off') . '^8.');
		$MTC->Send();
	}
	
	public function onTrack(Struct $Packet)
	{
		if ($this->Track == $Packet->Track)
			return PLUGIN_CONTINUE;
		else
			$this->Track = $Packet->Track;

		$file = "../data/pth/{$this->Track}.pth";
		if (!file_exists($file))
			return PLUGIN_CONTINUE;

		$pth = new pth($file);
		print_r($pth);
		$this->Path = array();
		foreach ($pth->Nodes as $Node)
			$this->Path[] = $Node->toPolyRoad();
	}
	
	public function onMCI(IS_MCI $MCI)
	{
		if ($this->LVS === FALSE)
			return PLUGIN_CONTINUE;

		foreach ($MCI->Info as $Info)
		{
			if (!$this->isInPoly($Info->X, $Info->Y, $this->Path[$Info->Node]))
				IS_MTC()->UCID($this->getClientByPLID($Info->PLID)->UCID)->Text('You are not on the track!')->Send();
		}
	}
}
?>