<?php

class IS_ISM extends Struct    	// InSim Multi
{
	const PACK = 'CCCxCxxxa32';
	const UNPACK = 'CSize/CType/CReqI/CZero/CHost/CSp1/CSp2/CSp3/a32HName';

	protected $Size = 40;				# 40
	protected $Type = ISP_ISM;			# ISP_ISM
	protected $ReqI = 0;				# usually 0 / or if a reply : ReqI as received in the TINY_ISM
	protected $Zero = null;

	public $Host;						# 0 = guest / 1 = host
	protected $Sp1 = null;
	protected $Sp2 = null;
	protected $Sp3 = null;

	public $HName;						# the name of the host joined or started
}; function IS_ISM() { return new IS_ISM; }