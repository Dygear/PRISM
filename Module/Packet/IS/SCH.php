<?php

class IS_SCH extends Struct    	// Single CHaracter
{
	const PACK = 'CCxxCCxx';
	const UNPACK = 'CSize/CType/CReqI/CZero/CCharB/CFlags/CSpare2/CSpare3';

	protected $Size = 8;				# 8
	protected $Type = ISP_SCH;			# ISP_SCH
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $CharB;						# key to press
	public $Flags;						# bit 0 : SHIFT / bit 1 : CTRL
	protected $Spare2 = null;
	protected $Spare3 = null;
}; function IS_SCH() { return new IS_SCH; }