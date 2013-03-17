<?php

class IS_MSO extends Struct // MSg Out - system messages and user messages
{
    const PACK = 'CCxxCCCCa128';
	const UNPACK = 'CSize/CType/CReqI/CZero/CUCID/CPLID/CUserType/CTextStart/a128Msg';

	protected $Size = 136;				# 136
	protected $Type = ISP_MSO;			# ISP_MSO
	protected $ReqI = null;			# 0
	protected $Zero = null;

	public $UCID = 0;					# connection's unique id (0 = host)
	public $PLID = 0;					# player's unique id (if zero, use UCID)
	public $UserType;					# set if typed by a user (see User Values below)
	public $TextStart;					# first character of the actual text (after player name)

	public $Msg;
}; function IS_MSO() { return new IS_MSO; }

// User Values (for UserType byte)

define('MSO_SYSTEM',	0);		// 0 - system message
define('MSO_USER',		1);		// 1 - normal visible user message
define('MSO_PREFIX',	2);		// 2 - hidden message starting with special prefix (see ISI)
define('MSO_O',			3);		// 3 - hidden message typed on local pc with /o command
define('MSO_NUM',		4);
$MSO = array(MSO_SYSTEM => 'MSO_SYSTEM', MSO_USER => 'MSO_USER', MSO_PREFIX => 'MSO_PREFIX', MSO_O => 'MSO_O', MSO_NUM => 'MSO_NUM');

// NOTE : Typing "/o MESSAGE" into LFS will send an IS_MSO with UserType = MSO_O