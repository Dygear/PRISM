<?php

class IS_MOD extends Struct // MODe : send to LFS to change screen mode
{
    const PACK = 'CCxxVVVV';
	const UNPACK = 'CSize/CType/CReqI/CZero/VBits16/VRR/VWidth/VHeight';

	protected $Size = 20;				# 20
	protected $Type = ISP_MOD;			# ISP_MOD
	public $ReqI;						# 0
	public $Zero;

	public $Bits16;						# set to choose 16-bit
	public $RR;							# refresh rate - zero for default
	public $Width;						# 0 means go to window
	public $Height;						# 0 means go to window
}; function IS_MOD() { return new IS_MOD; }
