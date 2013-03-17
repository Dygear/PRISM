<?php

class IS_SMALL extends Struct // General purpose 8 byte packet
{
    const PACK = 'CCCCV';
	const UNPACK = 'CSize/CType/CReqI/CSubT/VUVal';

	protected $Size = 8;				# always 8
	protected $Type = ISP_SMALL;		# always ISP_SMALL
	public $ReqI;						# 0 unless it is an info request or a reply to an info request
	public $SubT;						# subtype, from SMALL_ enumeration (e.g. SMALL_SSP)

	public $UVal;						# value (e.g. for SMALL_SSP this would be the OutSim packet rate)
}; function IS_SMALL() { return new IS_SMALL; }