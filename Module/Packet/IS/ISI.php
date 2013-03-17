<?php

namespace PRISM\Module\Packet;

class IS_ISI extends Struct // InSim Init - packet to initialise the InSim system
{
    const PACK = 'CCCxvvxCva16a16';
	const UNPACK = 'CSize/CType/CReqI/CZero/vUDPPort/vFlags/CSp0/CPrefix/vInterval/a16Admin/a16IName';

	protected $Size = 44;				# 44
	protected $Type = ISP_ISI;			# always ISP_ISI
	public $ReqI;						# If non-zero LFS will send an IS_VER packet
	protected $Zero = null;				# 0

	public $UDPPort;					# Port for UDP replies from LFS (0 to 65535)
	public $Flags;						# Bit flags for options (see below)

	protected $Sp0 = null;				# 0
	public $Prefix;						# Special host message prefix character
	public $Interval;					# Time in ms between NLP or MCI (0 = none)

	public $Admin;						# Admin password (if set in LFS)
	public $IName;						# A short name for your program
}; function IS_ISI() { return new IS_ISI; }