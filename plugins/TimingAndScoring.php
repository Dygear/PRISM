<?php
class TimingAndScoring extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'Timing & Scoring';
	const AUTHOR = 'Mark \'Dygear\' Tomlin';
	const VERSION = '0.3.0';
	const DESCRIPTION = 'Formula One™ Managaement Style Timeing & Scoring';

	public function __construct()
	{
		$this->registerPacket('onSplit', ISP_LAP, ISP_SPX);
	}

	private $Splits = array();

	public function onSplit(Struct $SLP)
	{	# ISP_LAP & ISP_SPX
		# Generalize the packet
		$sTime = ($SLP instanceof IS_LAP) ? 'LTime' : 'STime';
		$sSplit = ($SLP instanceof IS_LAP) ? 'LAP' : 'SP'.$SLP->Split;
		# Sanity check on the first time recorded.
		$iBest = (isset($this->Splits[$sSplit])) ? $this->Splits[$sSplit] : $SLP->$sTime;

		$sColor = '^3'; # Yellow (Default)
		if (!isset($this->Splits[$SLP->PLID][$sSplit]) OR $SLP->$sTime < $this->Splits[$SLP->PLID][$sSplit])
		{	# Personal Best
			$sColor = '^2'; # Green
			$this->Splits[$SLP->PLID][$sSplit] = $SLP->$sTime;
		}
		if (!isset($this->Splits[$sSplit]) OR $SLP->$sTime < $this->Splits[$sSplit])
		{	# Overall Best
			$sColor = '^5'; # Purple
			$this->Splits[$sSplit] = $SLP->$sTime;
		}

		# On Screen Display
		$this->display($SLP->PLID, $sColor, $SLP->$sTime, $iBest);
	}
	
	// User Interface
	# On Screen Display
	public function display($iPLID, $sColor, $iSTime, $iBTime)
	{
		$Player = $this->getPlayerByPLID($iPLID);

		# Skip displaying lap out data & AI.
		if ($iSTime >= 3600000 OR $Player->isAI())
			return;

		$sPName = $Player->PName . '^9';
		$sTime = $sColor . timeToStr($iSTime) . '^9';
		$sΔ = ((($sΔ = $iSTime - $iBTime) < 0) ? '^2-' : '^3+') . timeToStr(abs($sΔ)) . '^9';

//		Msg2Lfs()->PLID($iPLID)->Text("{$sPName} : {$sTime} ($sΔ)")->Send();

		$this->OSD($this->getClientByPLID($iPLID)->UCID, $sPName, $sTime, $sΔ);
	}

	public function OSD($iUCID, $sPName, $sTime, $sΔ)
	{
		$bName = new Button($iUCID, 'PName', 'FOM');
		$bName->L(40)->T(166)->W(40)->H(8);
		$bName->BStyle |= ISB_DARK + ISB_RIGHT;
		$bName->Text($sPName)->send();
	
		$bTime = new Button($iUCID, 'STime', 'FOM');
		$bTime->L(40)->T(174)->W(40)->H(8);
		$bTime->BStyle |= ISB_DARK + ISB_RIGHT;
		$bTime->Text($sTime)->send();
	
		$bΔ = new Button($iUCID, 'Delta', 'FOM');
		$bΔ->L(40)->T(182)->W(40)->H(8);
		$bΔ->BStyle |= ISB_DARK + ISB_RIGHT;
		$bΔ->Text($sΔ)->send();
	
		$this->createTimer('OSR', 10, Timer::CLOSE, array($iUCID));
	}

	public function OSR($iUCID)
	{
		ButtonManager::removeButtonsByGroup($iUCID, 'FOM');
	}
}
?>