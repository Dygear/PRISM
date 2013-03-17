<?php

class IS_SFP extends Struct // State Flags Pack
{
    const PACK = 'CCCxvCx';
	const UNPACK = 'CSize/CType/CReqI/CZero/vFlag/COffOn/CSp3';

	protected $Size = 8;				# 8
	protected $Type = ISP_SFP;			# ISP_SFP
	protected $ReqI;					# 0
	protected $Zero;

	public $Flag;						# the state to set
	public $OffOn;						# 0 = off / 1 = on
	protected $Sp3;						# spare
}; function IS_SFP() { return new IS_SFP; }