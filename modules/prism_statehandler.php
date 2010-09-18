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
	// Properties 
	public $clients = array();
	public $players = array();	# By design there is one here, and the other is refrence to this in the $this->clients->players array.
	public static $handles = array
	(
		# State handles
		ISP_TINY => 'onTiny',				# To Do.
		ISP_SMALL => 'onSmall',				# To Do.
		ISP_VER => 'onVersion',				# To Do.
		ISP_CPP => 'onCameraPosisionChange',# To Do.
		ISP_STA => 'onStateChange',			# To Do.
		ISP_RST => 'onRaceStart',			# To Do.
		ISP_ISM => 'onMultiPlayerStart',	# To Do.
		ISP_NLP => 'onNodeLapPlayer',		# To Do.
		ISP_MCI => 'onMultiCarInfo',		# To Do.
		ISP_REO => 'onReorder',				# To Do.
		ISP_AXI => 'onAutoXInfo',			# To Do.
		ISP_RIP => 'onReplayInformation',	# To Do.
		# Client handles
		ISP_NCN => 'onClientJoin',			# To Do.
		ISP_CNL => 'onClientLeave',			# To Do.
		ISP_CPR => 'onClientRename',		# To Do.
		ISP_TOC => 'onClientTakeOverCar',	# To Do.
		# Player handles
		ISP_FIN => 'onPlayerFinished',		# To Do.
		ISP_RES => 'onPlayerResult',		# To Do.
		ISP_NPL => 'onNewPlayer',			# To Do.
		ISP_PLP => 'onPlayerPits',			# To Do.
		ISP_PLL => 'onPlayerLeave',			# To Do.
	);

	// Constructor
	public function __construct()
	{
		global $PRISM;
		# Send out some info requests
		$ISP = new IS_TINY();
		$ISP->ReqI = 1;
		// Request every bit of information we can get.
		// This becomes our baseline that we use and update as needed.
		$ISP->SubT = TINY_SCP;	# Send Camera Pos (ISP_CPP)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_SST;	# Send STate info (ISP_STA)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_GTH;	# Get Time in Hundredths (SMALL_RTP)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_ISM;	# Get Multiplayer Info (ISP_ISM)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_NCN;	# get all connections (ISP_NCN)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_NPL;	# get all players (ISP_NPL)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_RES;	# get all results (ISP_RES)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_NLP;	# send an IS_NLP (ISP_NLP)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_MCI;	# send an IS_MCI (ISP_MCI)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_REO;	# send an IS_REO (ISP_REO)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_RST;	# send an IS_RST (ISP_RST)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_AXI;	# send an IS_AXI - AutoX Info (ISP_AXI)
		$PRISM->hosts->sendPacket($ISP);
		$ISP->SubT = TINY_RIP;	# send an IS_RIP - Replay Information Packet (ISP_RIP)
		$PRISM->hosts->sendPacket($ISP);
	}

	// Basicly the IS_VER Struct.
	private $Version;	# LFS version, e.g. 0.5Z28
	private $Product;	# Product : DEMO, S1, S2 or S3
	private $InSimVer;	# InSim Protocol Version

	// Get
	public function &getVersion()
	{
		return $this->Version;
	}
	public function &getProduct()
	{
		return $this->Product;
	}
	public function &getInSimVer()
	{
		return $this->InSimVer();
	}

	// Basicly the IS_CPP Struct.
	private $Pos;			# Position vector
	private $H;				# Heading - 0 points along Y axis
	private $P;				# Pitch   - 0 means looking at horizon
	private $R;				# Roll    - 0 means no roll
	private $FOV;			# 4-byte float : FOV in degrees
	private $Time;			# Time to get there (0 means instant + reset)

	public function &getPos()
	{
		return $this->Pos;
	}
	public function &getHeading()
	{
		return $this->H;
	}
	public function &getPitch()
	{
		return $this->P;
	}
	public function &getRoll()
	{
		return $this->R;
	}
	public function &getFieldOfView()
	{
		return $this->FOV;
	}
	public function &getTime()
	{
		return $this->Time;
	}

	// Both the IS_CPP & IS_STA.
	private $InGameCam;		# Which type of camera is selected
	private $ViewPLID;		# Unique ID of viewed player (0 = none)
	private $State;			# ISS state flags.

	public function &getInGameCam()
	{
		return $this->InGameCam;
	}
	public function &getViewPLID()
	{
		return $this->ViewPLID;
	}
	public function &getState()
	{
		return $this->State;
	}

	// Basicly the IS_STA Struct.
	private $ReplaySpeed;	# 4-byte float - 1.0 is normal speed
	private $NumConns;		# Number of connections including host
	private $NumFinished;	# Number finished or qualified
	private $RaceInProg;	# 0 = No Race, 1 = Race, 2 = Qualifying

	public function &getReplaySpeed()
	{
		return $this->ReplaySpeed;
	}
	public function &getNumConns()
	{
		return $this->NumConns;
	}
	public function &getNumFinished()
	{
		return $this->NumFinished;
	}
	public function &getRaceInProgress()
	{
		return $this->RaceInProg;
	}

	// Both IS_STA & IS_RST Data
	private $NumP;		# Number of Players in the Race.

	public function &getNumP()
	{
		return $this->NumP;
	}

	// Basicly the IS_STA & IS_RST Struct.
	private $RaceLaps;	# 0 if Qualifiying.
	private $QualMins;	# 0 if Race.
	private $Track;		# Short Track Name.
	private $Weather;	# 0,1,2...
	private $Wind;		# 0 = Off 1 = Weak 2 = Strong
	private $Flags;		# Race Flags (Must pit, can reset, etc.)
	private $NumNodes;	# Total number of nodes in the path.
	private $Finsih;	# Node index - Finish Line
	private $Split1;	# Node index - Split 1
	private $Split2;	# Node index - Split 2
	private $Split3;	# Node index - Split 3

	// Get
	public function &getRaceLaps()
	{
		return $this->RaceLaps;
	}
	public function &getQualMins()
	{
		return $this->QualMins;
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

	// Is
	public function isQaulifying()
	{
		return ($this->RaceLaps == 0) ? TRUE : FALSE;
	}
	public function isRace()
	{
		return ($this->QualMins == 0) ? TRUE : FALSE;
	}
	public function isPractice()
	{
		return ($this->RaceLaps == 0 && $this->QualMins == 0) ? TRUE : FALSE;
	}

	// Basicly the IS_ISM Struct.
	private $Host;				# 0 = guest / 1 = host
	private $HName;				# The name of the host joined or started

	// Get
	public function &getHName()
	{
		return $this->HName;
	}
	// is
	public function isHost()
	{
		return ($this->Host == 1) ? TRUE : FALSE;
	}

	// Callbacks for Handlers
	public function onVersion(IS_VER $VER)
	{
		$this->Version = $VER->Version;
		$this->Product = $VER->Product;
		$this->InSimVer = $VER->InSimVer;
	}
	public function onCameraPosisionChange(ISP_CPP $CPP)
	{
	
	}
	public function onStateChange(IS_STA $STA)
	{
		$this->ReplaySpeed = $STA->ReplaySpeed;
		$this->ISSState = $STA->State;
		$this->InGameCam = $STA->InGameCam;
		$this->ViewPLID = $STA->ViewPLID;
		$this->NumConns = $STA->NumConns;
		$this->NumFinished = $STA->NumFinished;
		$this->RaceInProg = $STA->RaceInProg;
		$this->NumP = $STA->NumP;
	}
	public function onRaceStart(IS_RST $RST)
	{
		$this->RaceLaps = $RaceLaps;
		$this->QualMins = $QualMins;
		$this->NumP = $NumP;
		$this->Track = $Track;
		$this->Weather = $Weather;
		$this->Wind = $Wind;
		$this->Flags = $Flags;
		$this->NumNodes = $NumNodes;
		$this->Finish = $Finish;
		$this->Split1 = $Split1;
		$this->Split2 = $Split2;
		$this->Split3 = $Split3;
	}
	public function onMultiPlayerStart(IS_ISM $ISM)
	{
		$this->Host = $ISM->Host;
		$this->HName = $ISM->HName;
	}

	private $PLID;			# Player's unique id (0 = player left before result was sent)
	private $TTime;			# Race time (ms)
	private $BTime;			# Best lap (ms)
	private $NumStops;		# Number of pit stops
	private $Confirm;		# Confirmation flags : disqualified etc - see below
	private $LapsDone;		# Laps completed
	private $Flags;			# Player flags : help settings etc - see below

	public function &getPLID()
	{
		return $this->PLID;
	}
	public function &getTTime()
	{
		return $this->TTime;
	}
	public function &getBTime()
	{
		return $this->BTime;
	}
	public function &getNumStops()
	{
		return $this->NumStops;
	}
	public function &getConfirm()
	{
		return $this->Confirm;
	}
	public function &getLapsDone()
	{
		return $this->LapsDone;
	}
	public function &getFlags();
	{
		return $this->Flags;
	}

	public function onPlayerFinished(IS_FIN $FIN)
	{
		$this->PLID = $FIN->PLID;
		$this->TTime = $FIN->TTime;
		$this->BTime = $FIN->BTime;
		$this->NumStops = $FIN->NumStops;
		$this->Confirm = $FIN->Confirm;
		$this->LapsDone = $FIN->LapsDone;
		$this->Flags = $FIN->Flags;
	}

	public function onPlayerResult(IS_RES $RES)
	{
	}

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
	# Small Callbacks
	public function onSmall(IS_SMALL $SMALL)
	{
		switch ($SMALL->SubT)
		{
			case SMALL_RTP:
				$this->Time = UVal;
				break;
		}
	}
	# Client Callbacks (I ask you Scawen, why did you muddle Client & Connection?)
	public function onNewClient(IS_NCN $NCN)
	{
		$this->clients[$NCN->UCID] = new ClientHandler($NCN);
	}
	public function onClientLeave(IS_CNL $CNL)
	{
		unset($this->clients[$CNL->UCID]);
	}
	public function onClientRename(IS_CPR $CPR)
	{
		$this->clients[$CPR->UCID]->onRename($CPR);
	}
	public function onTakeOverCar(IS_TOC $TOC)
	{
		# Move PLID from one Client->Player to the Other.
			# Copy $this->cleints[$TOC->OldUCID] to $this->cleints[$TOC->NewUCID];
			# Remove $this->cleints[$TOC->OldUCID];
			// With any luck, we should be able to just move the refrence handle over, not have to copy/move it over.
		# Update $this->players[$TOC->PLID]->UCID to Equal new UCID.
	}
	# Player Callbacks
	public function onNewPlayer(IS_NPL $NPL)
	{
		if (!isset($this->players[$NPL->PLID]))
			$this->players[$NPL->PLID] = new PlayerHandler($NPL);
		else
			$this->players[$NPL->PLID]->setInPits(FALSE);
	}
	public function onPlayerPits(IS_PLP $PLP)
	{
		$this->players[$PLP->PLID]->setInPits(TRUE);
	}
	public function onPlayerLeave(IS_PLL $PLL)
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
	private $Flags;			# 2 If Client is Remote

	// Construct
	public function __construct(IS_NCN $NCN)
	{
		$this->UCID = $NCN->UCID;
		$this->UName = $NCN->UName;
		$this->PName = $NCN->PName;
		$this->Admin = $NCN->Admin;	# Where this is 1, client should be given the ADMIN_ADMIN permission level.
		$this->Total = $NCN->Total;
		$this->Flags = $NCN->Flags;
	}

	public function onRename(IS_CPR $CPR)
	{
		$this->PName = $CPR->PName;
		$this->Plate = $CPR->Plate;
	}

	// Get
	public function &getUCID(){ return $this->UCID; }
	public function &getUName(){ return $this->UName; }
	public function &getPName(){ return $this->PName; }
	public function &getAdmin(){ return $this->Admin; }
	public function &getTotal(){ return $this->Total; }
	public function &getFlags(){ return $this->Flags; }

	// Is
	public function isAdmin(){ return ($this->Admin == 1) ? TRUE : FALSE; }
	public function isRemote(){ return ($this->Flags == 2) ? TRUE : FALSE; }
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
	private $SetF;			# Setup flags (see below)
	private $NumP;			# Number in race (same when leaving pits, 1 more if new)
	# Addon informaiton
	public $inPits;			# For when a player is in our list, but not on track this is TRUE.


	// Construct
	public function __construct(IS_NPL $NPL)
	{
		$this->UCID = $NPL->UCID;
		$this->PType = $NPL->PType;
		$this->Flags = $NPL->Flags;
		$this->PName = $NPL->PName;
		$this->Plate = $NPL->Plate;
		$this->CName = $NPL->CName;
		$this->SName = $NPL->SName;
		$this->Tyres = $NPL->Tyres;
		$this->HMass = $NPL->H_Mass;
		$this->HTRes = $NPL->H_TRes;
		$this->Model = $NPL->Model;
		$this->Pass = $NPL->Pass;
		$this->SetF = $NPL->SetF;
		$this->NumP = $NPL->NumP;
		$this->inPits = FALSE;
	}

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
	public function &getSetF(){ return $this->SetF; }
	public function &getNumP(){ return $this->NumP; }

	// Is
	public function isFemale(){ return ($this->Flags & 1) ? TRUE : FALSE; }
	public function isAI(){ return ($this->Flags & 2) ? TRUE : FALSE; }
	public function isRemote(){ return ($this->Flags & 4) ? TRUE : FALSE; }
	public function &isInPits(){ return $this->inPits; }
}

class UserHandler
{
	// Todo, this is going to be for the user targeting system.
}

?>