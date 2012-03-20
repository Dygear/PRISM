<?php
class LVS extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'LVS';
	const AUTHOR = 'PRISM Dev Team';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'Lap Verification System.';

	private $pth = null;
	private $lapValidation = array();
	private $onLap = array();
	private $onRoad = array();

	public function __construct()
	{
		$this->registerPacket('onTrackInfo', ISP_STA);
		$this->registerPacket('onNewPlayer', ISP_NPL);
		$this->registerPacket('onPlayerLeave', ISP_PLL);
		$this->registerPacket('onNewLap', ISP_LAP);
		$this->registerPacket('onVerification', ISP_HLV);
		$this->registerPacket('onCarInfo', ISP_MCI);

		$this->registerSayCommand('check', 'cmdValid', '<PLID> (LAP) - Check\'s to see if a lap is valid.');
		$this->registerSayCommand('plids', 'cmdListPLIDs', 'Gets a list of PLID\'s and the UName and PName connected with that ID.');
	}

	public function onTrackInfo(IS_STA $STA)
	{
		static $Track;
		if ($Track != $STA->Track)
			$Track = $STA->Track;

		$this->pth = new pth(ROOTPATH . '/data/pth/' . $Track . '.pth');
		//print_r($this->pth);
		console("Loaded $Track.pth");
		return PLUGIN_CONTINUE;
	}
	
	public function onNewPlayer(IS_NPL $NPL)
	{
		$this->onLap[$NPL->PLID] = 1;
		$this->lapValidation[$NPL->PLID] = array(1 => TRUE);
	}

	public function onPlayerLeave(IS_PLL $PLL)
	{
		unset($this->onLap[$PLL->PLID]);
		unset($this->lapValidation[$PLL->PLID]);
	}
	
	public function onNewLap(IS_LAP $LAP)
	{
		$this->onLap[$LAP->PLID] = $LAP->LapsDone;
		$this->lapValidation[$LAP->PLID][$this->onLap[$LAP->PLID]] = TRUE;
	}

	public function onVerification(IS_HLV $HLV)
	{
		if (!isset($this->lapValidation[$HLV->PLID]))
			return PLUGIN_CONTINUE; # In the case where the player that caused the HLV has already also left.

		if ($this->lapValidation[$HLV->PLID][$this->onLap[$HLV->PLID]] === FALSE)
			return PLUGIN_CONTINUE;	# It's already an invalid lap, we don't report it twice.

		$cl = $this->getClientByPLID($HLV->PLID);
		IS_MSX()->Msg("{$cl->PName}'s Lap is ^1invalid^9!")->Send();
		
		$this->lapValidation[$HLV->PLID][$this->onLap[$HLV->PLID]] = FALSE;
	}
	
	public function isValid($PLID, $LAP = NULL)
	{
		if ($LAP === NULL)
			$LAP = $this->onLap[$PLID];

		return $this->lapValidation[$PLID][$LAP];
	}
	
	public function onCarInfo(IS_MCI $MCI)
	{
	    if (!$this->pth) { return PLUGIN_CONTINUE; }
	    
		foreach ($MCI->Info as $CompCar)
		{
			if (!isset($this->lapValidation[$CompCar->PLID]))
				return PLUGIN_CONTINUE; # In the case where the player has already left.

			$isRoad = $this->pth->isOnRoad($CompCar->X, $CompCar->Y, $CompCar->Node);

			if (!isset($this->onRoad[$CompCar->PLID]))
				$this->onRoad[$CompCar->PLID] = NULL;

			if ($this->onRoad[$CompCar->PLID] == $isRoad)
				return; # They already know.

			if ($this->pth->isOnRoad($CompCar->X, $CompCar->Y, $CompCar->Node) === FALSE)
				IS_MTC()->PLID($CompCar->PLID)->Text('You are ^1off^9 the track!')->Send();
			else
				IS_MTC()->PLID($CompCar->PLID)->Text('You are ^2on^9 the track!')->Send();

			$this->onRoad[$CompCar->PLID] = $isRoad;

			if ($isRoad === TRUE OR $this->lapValidation[$CompCar->PLID][$this->onLap[$CompCar->PLID]] === FALSE)
				return PLUGIN_CONTINUE;	# It's already an invalid lap, we don't report it twice.

			IS_MSX()->Msg("{$this->getClientByPLID($CompCar->PLID)->PName}'s Lap is ^1invalid^9!")->Send();

			$this->lapValidation[$CompCar->PLID][$this->onLap[$CompCar->PLID]] = FALSE;
		}
	}
	
	public function cmdValid($cmd, $ucid)
	{
		$argc = count($argv = str_getcsv($cmd, ' '));

		$plid = (isset($argv[1])) ? $argv[1] : $this->getClientByUCID($ucid)->PLID;

		if ($this->isValid($plid, $argv[2]))
			IS_MTC()->UCID($ucid)->Text('Lap is valid.')->Send();
		else
			IS_MTC()->UCID($ucid)->Text('Lap is not valid.')->Send();

		return PLUGIN_HANDLED;
	}
	
	public function cmdListPLIDs($cmd, $ucid)
	{
		ksort($this->onLap);

		forEach ($this->onLap as $PLID => $LAP)
		{
			$cl = $this->getClientByPLID($PLID);
			IS_MTC()->UCID($ucid)->Text("{$PLID} : {$cl->UCID} - {$cl->UName} - {$cl->PName}")->Send();
		}
	}
}
?>