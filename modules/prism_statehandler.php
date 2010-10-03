<?php
/**
 * PHPInSimMod - State Module
 * @package PRISM
 * @subpackage State
 * @subpackage Users
 * @subpackage Clients
 * @subpackage Players
*/

class StateHandler extends PropertyMaster
{
	// Intrinsic Handles
	protected $handles = array
	(
		# State handles
#		ISP_ISI => 'onInSimInit',			# To Do. (1)
		ISP_VER => 'onVersion',				# To Do. (2)
		ISP_TINY => 'onTiny',				# To Do. (3)
		ISP_SMALL => 'onSmall',				# To Do. (4)
		ISP_STA => 'onStateChange',			# To Do. (5)
		ISP_CPP => 'onCameraPosisionChange',# To Do. (9)
		ISP_ISM => 'onMultiPlayerStart',	# To Do. (10)
		ISP_RST => 'onRaceStart',			# To Do. (17)
		ISP_REO => 'onReorder',				# To Do. (36)
		ISP_NLP => 'onNodeLapPlayer',		# To Do. (37)
		ISP_MCI => 'onMultiCarInfo',		# To Do. (38)
		ISP_AXI => 'onAutoXInfo',			# To Do. (43)
		ISP_RIP => 'onReplayInformation',	# To Do. (48)
		# Client handles
		ISP_NCN => 'onClientJoin',			# To Do. (18)
		ISP_CNL => 'onClientLeave',			# To Do. (19)
		ISP_CPR => 'onClientRename',		# To Do. (20)
		ISP_TOC => 'onClientTakeOverCar',	# To Do. (31)
		# Player handles
		ISP_NPL => 'onPlayerJoin',			# To Do. (21)
		ISP_PLP => 'onPlayerPits',			# To Do. (22)
		ISP_PLL => 'onPlayerLeave',			# To Do. (23)
		ISP_FIN => 'onPlayerFinished',		# To Do. (34)
		ISP_RES => 'onPlayerResult',		# To Do. (35)
	);

	public function dispatchPacket(Struct $Packet)
	{
		if (isset($this->handles[$Packet->Type]))
		{
			$handle = $this->handles[$Packet->Type];
			$this->$handle($Packet);
		}
	}
	// Extrinsic Properties
	public $clients = array();
	public $players = array();	# By design there is one here and a refrence to this in the $this->clients->players array.

	// Constructor
	public function __construct()
	{
		global $PRISM;
		# Send out some info requests
		$ISP = new IS_TINY();
		$ISP->ReqI = 1;
		// Request every bit of information we can get.
		// This becomes our baseline that we use and update as needed.
		$ISP->SubT(TINY_GTH)->Send();	# Get Time in Hundredths (SMALL_RTP)
		$ISP->SubT(TINY_SCP)->Send();	# Send Camera Pos (ISP_CPP)
		$ISP->SubT(TINY_SST)->Send();	# Send STate info (ISP_STA)
		$ISP->SubT(TINY_ISM)->Send();	# Get Multiplayer Info (ISP_ISM)
		$ISP->SubT(TINY_NCN)->Send();	# get all connections (ISP_NCN)
		$ISP->SubT(TINY_NPL)->Send();	# get all players (ISP_NPL)
		$ISP->SubT(TINY_RES)->Send();	# get all results (ISP_RES)
		$ISP->SubT(TINY_REO)->Send();	# send an IS_REO (ISP_REO)
		$ISP->SubT(TINY_RST)->Send();	# send an IS_RST (ISP_RST)
		$ISP->SubT(TINY_AXI)->Send();	# send an IS_AXI - AutoX Info (ISP_AXI)

		if (!$PRISM->hosts->getHostById()->isRelay())
		{
			$ISP->SubT(TINY_NLP)->Send();	# send an IS_NLP (ISP_NLP)
			$ISP->SubT(TINY_MCI)->Send();	# send an IS_MCI (ISP_MCI)
			$ISP->SubT(TINY_RIP)->Send();	# send an IS_RIP - Replay Information Packet (ISP_RIP)
		}
	}

	// Intrinsic Properties & Handlers
	public function packetHandler(Struct $Packet)
	{
		$handler &= $this->handles[$Packet->Type];
		if (isset($handler))
			$handler($Packet);

		return FALSE;
	}

	# IS_TINY (3)
	public function onTiny(IS_TINY $TINY)
	{
		// To Do.
	}
	
	# IS_SMALL (4)
	public function onSmall(IS_SMALL $SMALL)
	{
		// To Do.
	}

	# IS_VER (2)
	protected $Version;	# LFS version, e.g. 0.3G
	protected $Product;	# Product : DEMO or S1
	protected $InSimVer;	# InSim Version : increased when InSim packets change
	public function onVersion(IS_VER $VER)
	{
		$this->Version = $VER->Version;
		$this->Product = $VER->Product;
		$this->InSimVer = $VER->InSimVer;
	}

	# IS_STA (5)
	protected $ReplaySpeed;	# 1.0 is normal speed
	/** This was renamed from Flags to State as to not conflict with other Flags */
	protected $State;			# ISS state flags
	protected $InGameCam;		# Which type of camera is selected (see below)
	protected $ViewPLID;		# Unique ID of viewed player (0 = none)
	protected $NumP;			# Number of players in race
	protected $NumConns;		# Number of connections including host
	protected $NumFinished;	# Number finished or qualified
	protected $RaceInProg;	# 0 - No race / 1 - Race / 2 - Qualifying
	protected $QualMins;
	protected $RaceLaps;
	protected $Track;			# Short name for track e.g. FE2R
	protected $Weather;		# 0, 1 or 2.
	protected $Wind;			# 0 = Off 1 = Weak 2 = Strong
	public function onStateChange(IS_STA $STA)
	{
		$this->ReplaySpeed = $STA->ReplaySpeed;
		$this->State = $STA->Flags;
		$this->InGameCam = $STA->InGameCam;
		$this->ViewPLID = $STA->ViewPLID;
		$this->NumP = $STA->NumP;
		$this->NumConns = $STA->NumConns;
		$this->NumFinished = $STA->NumFinished;
		$this->RaceInProg = $STA->RaceInProg;
		$this->QualMins = $STA->QualMins;
		$this->RaceLaps = $STA->RaceLaps;
		$this->Track = $STA->Track;
		$this->Weather = $STA->Weather;
		$this->Wind = $STA->Wind;
	}

	# IS_CPP (9)
	protected $Pos;			# Position vector
	protected $Heading;		# heading - 0 points along Y axis
	protected $Pitch;			# pitch   - 0 means looking at horizon
	protected $Roll;			# roll    - 0 means no roll
	protected $FOV;			# FOV in degrees
	protected $Time;			# Time to get there (0 means instant + reset)
	public function onCameraPosisionChange(IS_CPP $CPP)
	{
		$this->Pos = $CPP->Pos;
		$this->Heading = $CPP->H;
		$this->Pitch = $CPP->P;
		$this->Roll = $CPP->R;
		$this->ViewPLID = $CPP->ViewPLID;
		$this->InGameCam = $CPP->InGameCam;
		$this->FOV = $CPP->FOV;
		$this->Time = $CPP->Time;
		$this->State = $CPP->Flags;
	}

	# IS_ISM (10)
	public $Host;			# 0 = guest / 1 = host
	public $HName;			# The name of the host joined or started.
	public function onMultiPlayerStart(IS_ISM $ISM)
	{
		$this->Host = $ISM->Host;
		$this->HName = $ISM->HName;
	}

	# IS_RST (17)
	public $Flags;			// race flags (must pit, can reset, etc)
	public $NumNodes;		// total number of nodes in the path
	public $Finish;			// node index - finish line
	public $Split1;			// node index - split 1
	public $Split2;			// node index - split 2
	public $Split3;			// node index - split 3
	public function onRaceStart(IS_RST $RST)
	{
		$this->RaceLaps = $RST->RaceLaps;
		$this->QualMins = $RST->QualMins;
		$this->NumP = $RST->NumP;
		$this->Track = $RST->Track;
		$this->Weather = $RST->Weather;
		$this->Wind = $RST->Wind;
		$this->Flags = $RST->Flags;
		$this->NumNodes = $RST->NumNodes;
		$this->Finish = $RST->Finish;
		$this->Split1 = $RST->Split1;
		$this->Split2 = $RST->Split2;
		$this->Split3 = $RST->Split3;
	}

	# IS_REO (36)
	public function onReorder(IS_REO $REO)
	{
		$this->NumP = $REO->NumP;
		$this->PLID = $REO->PLID;
	}

	# IS_NLP (37)
	protected $Info;	# Car Info For Each Player.
	public function onNodeLapPlayer(IS_NLP $NLP)
	{
		$this->NumP = $NLP->NumP;
		$this->Info = $NLP->Info;
	}

	# IS_MCI (38)
	protected $NumC;	# Number of valid CompCar structs in this packet.
	public function onMultiCarInfo(IS_MCI $MCI)
	{
		$this->NumC = $MCI->NumC;
		$this->Info = $MCI->Info;
	}

	# IS_AXI (43)
	protected $AXStart;	# Autocross start position
	protected $NumCP;		# Number of checkpoints
	protected $NumO;		# Number of objects
	protected $LName;		# The name of the layout last loaded (if loaded locally)
	public function onAutoXInfo(IS_AXI $AXI)
	{
		$this->AXStart = $AXI->AXStart;
		$this->NumCP = $AXI->NumCP;
		$this->NumO = $AXI->NumO;
		$this->LName = $AXI->LName;
	}

	# IS_RIP (48)
	protected $Error;		# 0 or 1 = OK / other values are listed below
	protected $MPR;		# 0 = SPR / 1 = MPR
	protected $Paused;	# Request : pause on arrival / reply : paused state
	protected $Options;	# Various options - see below
	protected $CTime;		# (hundredths) request : destination / reply : position
	protected $TTime;		# (hundredths) request : zero / reply : replay length
	protected $RName;		# zero or replay name - last byte must be zero
	public function onReplayInformation(IS_RIP $RIP)
	{
		$this->Error = $RIP->Error;
		$this->MPR = $RIP->MPR;
		$this->Paused = $RIP->Paused;
		$this->Options = $RIP->Options;
		$this->CTime = $RIP->CTime;
		$this->TTime = $RIP->TTime;
		$this->RName = $RIP->RName;
	}

	// Client handles
	# IS_NCN (18)
	public function onClientJoin(IS_NCN $NCN)
	{
		$this->clients[$NCN->UCID] = new ClientHandler($NCN);
	}
	# IS_CNL (19)
	public function onClientLeave(IS_CNL $CNL)
	{
		unset($this->clients[$CNL->UCID]);
	}
	# IS_CPR (20)
	public function onClientRename(IS_CPR $CPR)
	{
		$this->clients[$CPR->UCID]->onRename($CPR);
	}
	# IS_TOC (31)
	public function onClientTakeOverCar(IS_TOC $TOC)
	{
		console(__METHOD__.'(To Do)');
	}

	// Player handles
	# IS_NPL (21)
	public function onPlayerJoin(IS_NPL $NPL)
	{
		$this->players[$NPL->PLID] = new PlayerHandler($NPL);
		$this->clients[$NPL->UCID]->players[$NPL->PLID] = &$this->players[$NPL->PLID];
	}
	# IS_PLP (22)
	public function onPlayerPits(IS_PLP $PLP)
	{
		$this->players[$PLP->PLID]->onPitGarage($PLP);
	}
	# IS_PLL (23)
	public function onPlayerLeave(IS_PLL $PLL)
	{
		unset($this->players[$PLL->PLID]); # Should unset it everywhere.
	}
	# IS_FIN (34)
	public function onPlayerFinished(IS_FIN $FIN)
	{
		console(__METHOD__.'(To Do)');
	}
	# IS_RES (35)
	public function onPlayerResult(IS_RES $RES)
	{
		console(__METHOD__.'(To Do)');
	}
}

class ClientHandler extends PropertyMaster
{
	public static $handles = array
	(
		ISP_NCN => '__construct',	# 18
		ISP_CNL => 'onLeave',		# 19
		ISP_CPR => 'onRename',		# 20
		ISP_TOC => 'onTakeOverCar',	# 31
	);
	public $players = array();

	// Baiscly the IS_NCN Struct.
	protected $UCID;			# Connection's Unique ID (0 = Host)
	protected $UName;			# UserName
	protected $PName;			# PlayerName
	protected $Admin;			# TRUE If Client is Admin.
	protected $Total;			# Number of Connections Including Host
	protected $Flags;			# 2 If Client is Remote

	// Construct
	public function __construct(IS_NCN $NCN)
	{
		$this->UCID = $NCN->UCID; # Where this is 0, client should be given the ADMIN_SERVER permission level.
		$this->UName = $NCN->UName;
		$this->PName = $NCN->PName;
		$this->Admin = $NCN->Admin;	# Where this is 1, client should be given the ADMIN_ADMIN permission level.
		$this->Total = $NCN->Total;
		$this->Flags = $NCN->Flags;

		global $PRISM;
		if ($this->UCID == 0)
			$PRISM->admins->addAccount($this->UName, '', ADMIN_SERVER, $PRISM->hosts->getCurrentHost(), FALSE);
		else if ($this->Admin == TRUE)
			$PRISM->admins->addAccount($this->UName, '', ADMIN_ADMIN, $PRISM->hosts->getCurrentHost(), FALSE);
	}

	public function onRename(IS_CPR $CPR)
	{
		$this->PName = $CPR->PName;
		$this->Plate = $CPR->Plate;
	}
	
	// Is
	public function isAdmin(){ return ($this->UCID == 0 || $this->Admin == 1) ? TRUE : FALSE; }
	public function isRemote(){ return ($this->Flags == 2) ? TRUE : FALSE; }
}

class PlayerHandler extends PropertyMaster
{
	public static $handles = array(
#		ISP_NPL => '__construct',
		ISP_PLL => 'onPlayerLeave',			# To Do.
		ISP_PLP => 'onPlayerPits',			# To Do.
		ISP_FIN => 'onPlayerFinished',		# To Do.
		ISP_RES => 'onPlayerResult',		# To Do.
	);

	// Basicly the IS_NPL Struct.
	protected $UCID;			# Connection's Unique ID
	protected $PType;			# Bit 0 : female / bit 1 : AI / bit 2 : remote
	protected $Flags;			# Player flags
	protected $PName;			# Nickname
	protected $Plate;			# Number plate - NO ZERO AT END!
	protected $CName;			# Car name
	protected $SName;			# Skin name - MAX_CAR_TEX_NAME
	protected $Tyres;			# Compounds
	protected $HMass;			# Added mass (kg)
	protected $HTRes;			# Intake restriction
	protected $Model;			# Driver model
	protected $Pass;			# Passengers byte
	protected $SetF;			# Setup flags (see below)
	protected $NumP;			# Number in race (same when leaving pits, 1 more if new)
	# Addon informaiton
	public $inPits;			# For when a player is in our list, but not on track this is TRUE.

	// Constructor
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

	public function onPitGarage(IS_PLP $PLP)
	{
		$this->inPits = TRUE;
	}

	// Logic
	public function isFemale(){ return ($this->Flags & 1) ? TRUE : FALSE; }
	public function isAI(){ return ($this->Flags & 2) ? TRUE : FALSE; }
	public function isRemote(){ return ($this->Flags & 4) ? TRUE : FALSE; }
	public function &isInPits(){ return $this->inPits; }
}

class UserHandler
{
	// Todo, this is going to be for the user targeting system.
}

/**
 * Property Master allows for us the retreive read only properties on our classes.
*/
abstract class PropertyMaster
{
	public function __get($property)
	{
		return (isset($this->$property)) ? $this->$property : NULL;
	}
}

?>