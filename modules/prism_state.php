<?php
/**
 * PHPInSimMod - State Module
 * @package PRISM
 * @subpackage State
 * @subpackage Users
 * @subpackage Clients
 * @subpackage Players
*/

class StateHandler
{
	// Basicly the IS_RST Struct.
	private $RaceLaps;	# 0 if Qualifiying.
	private $QualMins;	# 0 if Race.
	private $NumP;		# Number of Players in the Race.
	private $Track;		# Short Track Name.
	private $Weather;
	private $Wind;		
	private $Flags;		# Race Flags (Must pit, can reset, etc.)
	private $NumNodes;	# Total number of nodes in the path.
	private $Finsih;	# Node index - Finish Line
	private $Split1;	# Node index - Split 1
	private $Split2;	# Node index - Split 2
	private $Split3;	# Node index - Split 3
	# Addons to the IS_RST Struct.
	private $clients = array();
	private $players = array();	# By design there is one here, and the other is refrence to this in the $this->clients->players array.

	// Constructor
	public function __construct()
	{
		global $PRISM;
		# Send out some info requests
		$ISP = new IS_TINY();
		$ISP->ReqI = 1;
		$ISP->SubT = TINY_NCN;
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_NPL;
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_RES;
		$PRISM->hosts->sendPacket($ISP);
	}

	// Set
	private function setRaceLaps($RaceLaps)
	{
		$this->RaceLaps = $RaceLaps;
	}
	private function setQualMins($QualMins)
	{
		$this->QualMins = $QualMins;
	}
	private function setNumP($NumP)
	{
		$this->NumP = $NumP;
	}
	private function setTrack($Track)
	{
		$this->Track = $Track;
	}
	private function setWeather($Weather)
	{
		$this->Weather = $Weather;
	}
	private function setWind($Wind)
	{
		$this->Wind = $Wind;
	}
	private function setFlags($Flags)
	{
		$this->Flags = $Flags;
	}
	private function setNumNodes($NumNodes)
	{
		$this->NumNodes = $NumNodes;
	}
	private function setFinish($Finish)
	{
		$this->Finish = $Finish;
	}
	private function setSplit1($Split1)
	{
		$this->Split1 = $Split1;
	}
	private function setSplit2($Split2)
	{
		$this->Split2 = $Split2;
	}
	private function setSplit3($Split3)
	{
		$this->Split3 = $Split3;
	}
	
	// Get
	public function &getRaceLaps()
	{
		return $this->RaceLaps;
	}
	public function &getQualMins()
	{
		return $this->QualMins;
	}
	public function &getNumP()
	{
		return $this->NumP;
	}
	public function &getTrack()
	{
		return $this->Track;
	}
	public function &getWeather()
	{
		return $this->Weather;
	}
	public function &getWind()
	{
		return $this->Wind;
	}
	public function &getFlags()
	{
		return $this->Flags;
	}
	public function &getNumNodes()
	{
		return $this->NumNodes;
	}
	public function &getFinish()
	{
		return $this->Finish;
	}
	public function &getSplit1()
	{
		return $this->Split1;
	}
	public function &getSplit2()
	{
		return $this->Split2;
	}
	public function &getSplit3()
	{
		return $this->Split3;
	}

	// Handles
	# Tiny Callbacks
	public function onTiny(IS_TINY $TINY)
	{
		switch ($TINY->SubT)
		{
			# When all players are cleared from race (e.g. /clear) LFS sends this IS_TINY
			case TINY_CLR:
				$this->players = array();
				break;
			# When a race ends (return to game setup screen) LFS sends this IS_TINY
			case TINY_REN:
				break;
		}
	}
	# Race Callbacks
	public function onRaceStart(IS_RST $RST)
	{
		
	}
	# State Callbacks
	protected function onStatusChange(IS_STA $STA)
	{
	
	}
	protected function onTakeOverCar(IS_TOC $TOC)
	{
		# Move PLID from one Client->Player to the Other.
			# Copy $this->cleints[$TOC->OldUCID] to $this->cleints[$TOC->NewUCID];
			# Remove $this->cleints[$TOC->OldUCID];
			// With any luck, we should be able to just move the refrence handle over, not have to copy/move it over.
		# Update $this->players[$TOC->PLID]->UCID to Equal new UCID.
	}
	# Client Callbacks (I ask you Scawen, why did you muddle Client & Connection?)
	protected function onNewConnection(IS_NCN $NCN)
	{
		$this->clients[$NCN->UCID] = new ClientHandler($NCN);
	}
	protected function onConnectionLeave(IS_CNL $CNL)
	{
		unset($this->clients[$CNL->UCID]);
	}
	protected function onConnectionRename(IS_CPR $CPR)
	{
#		$this->clients[$CPR->UCID]->rename($CPR->PName);
#		$this->players[$CPR->UCID]->replate($CPR->Plate); # Not sure how this is going to work yet.
	}
	# Player Callbacks
	protected function onNewPlayer(IS_NPL $NPL)
	{
		if (!isset($this->players[$NPL->PLID]))
			$this->players[$NPL->PLID] = new PlayerHandler($NPL);
		else
			$this->players[$NPL->PLID]->setInPits(FALSE);
	}
	protected function onPlayerPits(IS_PLP $PLP)
	{
		$this->players[$PLP->PLID]->setInPits(TRUE);
	}
	protected function onPlayerLeave(IS_PLL $PLL)
	{
		unset($this->players[$PLL->UCID]);
	}
}

class ClientHandler
{
	// Baiscly the IS_NCN Struct.
	private $UCID;			# Connection's Unique ID (0 = Host)
	private $UName;			# UserName
	private $PName;			# PlayerName
	private $Admin;			# TRUE If Client is Admin.
	private $Total;			# Number of Connections Including Host
	private $Flags;			# If 2 (0010) Client is Remote.

	private $players = array(); # Players array (Refrence to PlayersHandler);

	// Construct
	public function __construct(IS_NCN $NCN)
	{
		$this->setUCID($NCN->UCID);
		$this->setUName($NCN->UName);
		$this->setPName($NCN->PName);
		$this->setAdmin($NCN->Admin);
		$this->setTotal($NCN->Total);
		$this->setFlags($NCN->Flags);
	}

	// Set
	private function setUCID($UCID){ $this->UCID = $UCID; }
	private function setUName($UName){ $this->UName = $UName; }
	private function setPName($PName){ $this->PName = $PName; }
	private function setAdmin($Admin){ $this->Admin = $Admin; }
	private function setTotal($Total){ $this->Total = $Total; }
	private function setFlags($Flags){ $this->Flags = $Flags; }

	// Get
	public function &getUCID(){ return $this->UCID; }
	public function &getUName(){ return $this->UName; }
	public function &getPName(){ return $this->PName; }
	public function &getAdmin(){ return $this->Admin; }
	public function &getTotal(){ return $this->Total; }
	public function &getFlags(){ return $this->Flags; }

	// Is
	public function isAdmin(){ return ($this->Admin == 1); }
	public function isRemote(){ return ($this->Flags == 2); }
}

class PlayerHandler
{
	// Basicly the IS_NPL Struct.
	private $UCID;			# Connection's Unique ID
	private $PType;			# Bit 0 : female / bit 1 : AI / bit 2 : remote
	private $Flags;			# Player flags
	private $PName;			# Nickname
	private $Plate;			# Number plate - NO ZERO AT END!
	private $CName;			# Car name
	private $SName;			# Skin name - MAX_CAR_TEX_NAME
	private $Tyres;			# Compounds
	private $HMass;			# Added mass (kg)
	private $HTRes;			# Intake restriction
	private $Model;			# Driver model
	private $Pass;			# Passengers byte
	private $Flags;			# Setup flags (see below)
	private $NumP;			# Number in race (same when leaving pits, 1 more if new)
	# Addon informaiton
	public $inPits;			# For when a player is in our list, but not on track this is TRUE.

	// Construct
	public function __construct(IS_NPL $NPL)
	{
		$this->setUCID($NPL->UCID);
		$this->setPType($NPL->PType);
		$this->setFlags($NPL->Flags);
		$this->setPName($NPL->PName);
		$this->setPlate($NPL->Plate);
		$this->setCName($NPL->CName);
		$this->setSName($NPL->SName);
		$this->setTyres($NPL->Tyres);
		$this->setHMass($NPL->H_Mass);
		$this->setHTRes($NPL->H_TRes);
		$this->setModel($NPL->Model);
		$this->setPass($NPL->Pass);
		$this->setSetF($NPL->SetF);
		$this->setNumP($NPL->NumP);
		$this->setInPits(FALSE);
	}

	// Set
	private function setUCID($UCID){ $this->UCID = $UCID; }
	private function setPType($PType){ $this->PType = $PType; }
	private function setFlags($Flags){ $this->Flags = $Flags; }
	private function setPName($PName){ $this->PName = $PName; }
	private function setPlate($PLate){ $this->Plate = $Plate; }
	private function setCName($CName){ $this->CName = $CName; }
	private function setSName($SName){ $this->SName = $SName; }
	private function setTyres($Tyres){ $this->Tyres = $Tyres; }
	private function setHMass($HMass){ $this->HMass = $HMAss; }
	private function setHTRes($HTRes){ $this->HTRes = $HTRes; }
	private function setModel($Model){ $this->Model = $Model; }
	private function setPass($Pass){ $this->Pass = $Pass; }
	private function setFlags($Flags){ $this->Flags = $Flags; }
	private function setNumP($NumP){ $this->NumP = $NumP; }
	public function setInPits($InPits){ $this->inPits = $InPits; }

	// Get
	public function &getUCID(){ return $this->UCID; }
	public function &getPType(){ return $this->PType; }
	public function &getFlags(){ return $this->Flags; }
	public function &getPName(){ return $this->PName; }
	public function &getPlate(){ return $this->Plate; }
	public function &getCName(){ return $this->CName; }
	public function &getSName(){ return $this->SName; }
	public function &getTyres(){ return $this->Tyres; }
	public function &getHMass(){ return $this->HMass; }
	public function &getHTRes(){ return $this->HTRes; }
	public function &getModel(){ return $this->Model; }
	public function &getPass(){ return $this->Pass; }
	public function &getFlags(){ return $this->Flags; }
	public function &getNumP(){ return $this->NumP; }

	// Is
	public function isFemale(){ return ($this->Flags & 1); }
	public function isAI(){ return ($this->Flags & 2); }
	public function isRemote(){ return ($this->Flags & 4); }
	public function &isInPits(){ return $this->inPits; }
}

class UserHandler
{

}

?>