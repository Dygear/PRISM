<?php

namespace PRISM\Module;

use PRISM\Module\PropertyMaster;

class ClientHandler extends \PRISM\Module\PropertyMaster
{
    public static $handles = array
	(
		ISP_NCN => '__construct',	# 18
		ISP_CNL => '__destruct',	# 19
		ISP_CPR => 'onRename',		# 20
		ISP_TOC => 'onTakeOverCar'	# 31
	);
	public $players = array();

	public function dispatchPacket(Struct $Packet)
	{
		if (isset($this->handles[$Packet->Type])) {
			$handle = $this->handles[$Packet->Type];
			$this->$handle($Packet);
		}
	}

	// Baiscly the IS_NCN Struct.
	protected $UCID;			# Connection's Unique ID (0 = Host)
	protected $UName;			# UserName
	protected $PName;			# PlayerName
	protected $Admin;			# TRUE If Client is Admin.
	protected $Total;			# Number of Connections Including Host
	protected $Flags;			# 2 If Client is Remote

	// Construct
	public function __construct(IS_NCN $NCN, StateHandler $parent)
	{
		$this->parent = $parent;
	
		$this->UCID = $NCN->UCID; # Where this is 0, client should be given the ADMIN_SERVER permission level.
		$this->UName = $NCN->UName;
		$this->PName = $NCN->PName;
		$this->Admin = $NCN->Admin;	# Where this is 1, client should be given the ADMIN_ADMIN permission level.
		$this->Total = $NCN->Total;
		$this->Flags = $NCN->Flags;

		global $PRISM;
        
		if ($this->UCID == 0) {
			$PRISM->admins->addAccount('*'.$PRISM->hosts->getCurrentHost(), '', ADMIN_SERVER, $PRISM->hosts->getCurrentHost(), false);
		} else if ($this->Admin == true) {
			$PRISM->admins->addAccount($this->UName, '', ADMIN_ADMIN, $PRISM->hosts->getCurrentHost(), false);
		}
		
		$this->PRISM = ($PRISM->admins->adminExists($NCN->UName)) ? $PRISM->admins->getAdminInfo($NCN->UName) : false;
	}

	public function __destruct()
	{
		unset($this);
	}

	public function onRename(IS_CPR $CPR)
	{
		$this->PName = $CPR->PName;
		$this->Plate = $CPR->Plate;
	}

	public function onTakeOverCar(IS_TOC $TOC)
	{
		# Makes a copy of the orginal, and adds it to the new client.
		$this->parent->clients[$TOC->NewUCID]->players[$TOC->PLID] &= $this->parent->players[$TOC->PLID];
		# Removes the copy from this class, but should not garbage collect it, because it's copyied in the new class.
		unset($this->players[$TOC->PLID]);
	}
	
	// Is
	public function isAdmin(){ return ($this->isLFSAdmin() || $this->isPRISMAdmin) ? TRUE : FALSE; }
	public function isLFSAdmin(){ return ($this->UCID == 0 || $this->Admin == 1) ? TRUE : FALSE; }
	public function isPRISMAdmin(){ return !!$this->PRISM; }
	public function isRemote(){ return ($this->Flags == 2) ? TRUE : FALSE; }
	public function getAccessFlags(){ return $this->PRISM['accessFlags']; }
	public function getConnection(){ return $this->PRISM['connection']; }
	public function isTemporary(){ return $this->PRISM['temporary']; }
}
