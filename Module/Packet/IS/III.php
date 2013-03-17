<?php

class IS_III extends Struct // InsIm Info - /i message from user to host's InSim
{
    const PACK = 'CCxxCCxxa64';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CPLID/CSp2/CSp3/a64Msg';

	protected $Size = 72;				# 72
	protected $Type = ISP_III;			# ISP_III
	protected $ReqI = 0;				# 0
	protected $Zero = null;

	public $UCID = 0;					# connection's unique id (0 = host)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $Msg;
}; function IS_III() { return new IS_III; }
