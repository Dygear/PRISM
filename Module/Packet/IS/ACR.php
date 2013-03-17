<?php

class IS_ACR extends Struct // Admin Command Report - any user typed an admin command
{
    const PACK = 'CCxxCCxxa64';
	const UNPACK = 'CSize/CType/xReqI/xZero/CUCID/CAdmin/CResult/xSp3/a64Text';

	protected $Size = 72;				# 72
	protected $Type = ISP_ACR;			# ISP_ACR
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $UCID;						# connection's unique id (0 = host)
	public $Admin;						# set if user is an admin
	public $Result;						# 1 - processed / 2 - rejected / 3 - unknown command
	private $Sp3;

	public $Text;
}; function IS_ACR() { return new IS_ACR; }