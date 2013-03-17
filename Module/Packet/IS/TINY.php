<?php

namespace PRISM\Module\Packet;

class IS_TINY extends Struct // General purpose 4 byte packet
{
    const PACK = 'CCCC';
	const UNPACK = 'CSize/CType/CReqI/CSubT';

	protected $Size = 4;				# always 4
	protected $Type = ISP_TINY;			# always ISP_TINY
	public $ReqI;						# 0 unless it is an info request or a reply to an info request
	public $SubT;						# subtype, from TINY_ enumeration (e.g. TINY_RACE_END)
}; function IS_TINY() { return new IS_TINY; }