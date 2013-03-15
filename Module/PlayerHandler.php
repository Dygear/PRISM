<?php
/**
 * PHPInSimMod - Connections Module
 * @package PRISM
 * @subpackage Players
*/

namespace PRISM\Module;

use PRISM\Module\HostHandler;
use PRISM\Module\PropertyMaster;

class PlayerHandler extends\PRISM\Module\PropertyMaster
{
    public static $handles = array(
		ISP_NPL => '__construct',	# 21
		ISP_PLL => '__destruct',	# 23
		ISP_PLP => 'onPits',		# 22
		ISP_FIN => 'onFinished',	# 34
		ISP_RES => 'onResult',		# 35
		ISP_TOC => 'onTakeOverCar',	# 31
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
	public function __construct(IS_NPL $NPL, StateHandler $parent)
	{
		$this->parent = $parent;
		$this->onNPL($NPL);
	}

	public function __destruct()
	{
		unset($this);
	}

	private function onNPL(IS_NPL $NPL)
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

	public function onPits(IS_PLP $PLP)
	{
		$this->inPits = TRUE;
	}
	
	# Special case, handled within the parent class's onPlayerPacket method.
	public function onLeavingPits(IS_NPL $NPL)
	{
		$this->onNPL($NPL);
	}

	public function onTakeOverCar(IS_TOC $TOC)
	{
		$this->UCID = $TOC->NewUCID;
		$this->PName = $this->parent->clients[$TOC->NewUCID]->PName;
	}

	protected $finished = FALSE;
	public function onFinished(IS_FIN $FIN)
	{
		$this->finished = TRUE;
	}
	
	protected $result = array();
	public function onResult(IS_RES $RES)
	{
		$this->result[] = $RES;
	}

	// Logic
	public function isFemale(){ return ($this->PType & 1) ? TRUE : FALSE; }
	public function isAI(){ return ($this->PType & 2) ? TRUE : FALSE; }
	public function isRemote(){ return ($this->PType & 4) ? TRUE : FALSE; }
	public function &isInPits(){ return $this->inPits; }
}
